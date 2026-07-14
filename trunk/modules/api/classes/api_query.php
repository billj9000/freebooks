<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/api/classes/api_query.php
//
// Read query engine for FreeBooks REST API.
//
// Public contract:
//   api_query::run($resource, $params)  →  ['data'=>[...], 'meta'=>[...]]
//   api_query::error(string $msg)       →  throw ApiQueryException
//
// Supported params (all optional):
//   fields            JSON array of public field names, e.g. ["id","primary_name"]
//   filters           JSON array of [field, operator, value] triples  (AND-combined)
//   or_filters        JSON array of [field, operator, value] triples  (OR-combined, then AND'd with filters)
//   order_by          "field asc|desc"
//   limit_start       int  (default 0)
//   limit_page_length int  (default API_DEFAULT_PAGE_LENGTH, max API_MAX_PAGE_LENGTH)
//
// Operators: = != < > <= >= like not like in not in between is set is not set
//
// Extending in a later release:
//   - New operators  → add one entry to $operators below; no other change needed.
//   - Per-field types → extend the 'type' key in the resource field_map() for casting.
//

class ApiQueryException extends RuntimeException {}

class api_query {

  // -----------------------------------------------------------------------
  // Operator lookup table.
  // Each entry: ['scalar'=>bool, 'build'=>callable($col, $val)]
  //   scalar        — true: value is a single scalar; false: value is an array
  //   ignores_value — true: value is irrelevant (is set / is not set)
  //   build($col, $val) — returns a SQL snippet; $col and scalar $val are already escaped
  // To add an operator, append one entry here.  Nothing else changes.
  // -----------------------------------------------------------------------
  private static array $operators = [];

  private static function operators(): array {
    if (!empty(self::$operators)) return self::$operators;
    self::$operators = [
      '='          => ['scalar' => true,  'build' => fn($c,$v) => "$c = '$v'"],
      '!='         => ['scalar' => true,  'build' => fn($c,$v) => "$c != '$v'"],
      '<'          => ['scalar' => true,  'build' => fn($c,$v) => "$c < '$v'"],
      '>'          => ['scalar' => true,  'build' => fn($c,$v) => "$c > '$v'"],
      '<='         => ['scalar' => true,  'build' => fn($c,$v) => "$c <= '$v'"],
      '>='         => ['scalar' => true,  'build' => fn($c,$v) => "$c >= '$v'"],
      'like'       => ['scalar' => true,  'build' => fn($c,$v) => "$c LIKE '$v'"],
      'not like'   => ['scalar' => true,  'build' => fn($c,$v) => "$c NOT LIKE '$v'"],
      'in'         => ['scalar' => false, 'build' => function($c,$v) {
          if (!is_array($v) || empty($v)) throw new ApiQueryException("'in' requires a non-empty array value");
          $list = implode(',', array_map(fn($x) => "'" . db_input($x) . "'", $v));
          return "$c IN ($list)";
        }],
      'not in'     => ['scalar' => false, 'build' => function($c,$v) {
          if (!is_array($v) || empty($v)) throw new ApiQueryException("'not in' requires a non-empty array value");
          $list = implode(',', array_map(fn($x) => "'" . db_input($x) . "'", $v));
          return "$c NOT IN ($list)";
        }],
      'between'    => ['scalar' => false, 'build' => function($c,$v) {
          if (!is_array($v) || count($v) !== 2) throw new ApiQueryException("'between' requires a 2-element array value");
          $lo = db_input($v[0]);
          $hi = db_input($v[1]);
          return "$c BETWEEN '$lo' AND '$hi'";
        }],
      'is set'     => ['scalar' => true,  'ignores_value' => true, 'build' => fn($c,$v) => "($c IS NOT NULL AND $c != '')"],
      'is not set' => ['scalar' => true,  'ignores_value' => true, 'build' => fn($c,$v) => "($c IS NULL OR $c = '')"],
    ];
    return self::$operators;
  }

  // -----------------------------------------------------------------------
  // Main entry point.
  // $resource  — an object implementing field_map(), base_from(),
  //              base_where(), default_fields(), post_process()
  // $params    — associative array from $_GET (all string-valued)
  // Returns    — ['data'=>array, 'meta'=>array]
  // Throws ApiQueryException on validation error (caller emits 400).
  // -----------------------------------------------------------------------
  public static function run(object $resource, array $params): array {
    global $db;

    $field_map = $resource->field_map();

    // ── 1. Parse requested fields ──────────────────────────────────────────
    $requested_fields = self::parse_fields($params, $field_map, $resource->default_fields());

    // Separate virtual fields (handled by post_process) from SQL fields
    $sql_fields    = [];
    $virtual_fields = [];
    foreach ($requested_fields as $name) {
      if (!empty($field_map[$name]['virtual'])) {
        $virtual_fields[] = $name;
      } else {
        $sql_fields[] = $name;
      }
    }
    // Always select the primary key if not explicitly requested (needed for post_process joins)
    $pk = $resource->primary_key ?? 'id';
    if (!in_array($pk, $sql_fields) && !empty($virtual_fields)) {
      $sql_fields[] = $pk;
    }

    // ── 2. Build SELECT column list ────────────────────────────────────────
    $select_cols = array_map(fn($name) => $field_map[$name]['col'] . ' AS ' . $name, $sql_fields);

    // ── 3. Parse and validate filters → WHERE clauses ─────────────────────
    $where_parts = self::parse_filters($params, $field_map);
    $or_clause   = self::parse_or_filters($params, $field_map);
    if ($or_clause !== '') $where_parts[] = $or_clause;
    $base_where  = $resource->base_where();
    if ($base_where !== '') array_unshift($where_parts, "($base_where)");
    $where_sql   = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

    // ── 4. Parse ORDER BY ──────────────────────────────────────────────────
    $order_sql = self::parse_order_by($params, $field_map);

    // ── 5. Parse pagination ────────────────────────────────────────────────
    $limit_start  = max(0, (int)($params['limit_start'] ?? 0));
    $limit_length = min(
      API_MAX_PAGE_LENGTH,
      max(1, (int)($params['limit_page_length'] ?? API_DEFAULT_PAGE_LENGTH))
    );

    $from_sql = $resource->base_from();

    // ── 6. Count query ─────────────────────────────────────────────────────
    $count_sql = "SELECT COUNT(*) AS total FROM $from_sql $where_sql";
    $count_res = $db->Execute($count_sql);
    $total     = (int)($count_res->fields['total'] ?? 0);

    // ── 7. Data query ──────────────────────────────────────────────────────
    $data_sql = sprintf(
      "SELECT %s FROM %s %s %s LIMIT %d, %d",
      implode(', ', $select_cols),
      $from_sql,
      $where_sql,
      $order_sql,
      $limit_start,
      $limit_length
    );
    $data_res = $db->Execute($data_sql);
    $rows = [];
    while (!$data_res->EOF) {
      $rows[] = $data_res->fields;
      $data_res->MoveNext();
    }

    // ── 8. Post-process (virtual fields, e.g. invoice lines) ───────────────
    if (!empty($virtual_fields)) {
      $rows = $resource->post_process($rows, $virtual_fields);
    }

    return [
      'data' => $rows,
      'meta' => [
        'limit_start'       => $limit_start,
        'limit_page_length' => $limit_length,
        'count'             => count($rows),
        'total'             => $total,
      ],
    ];
  }

  // -----------------------------------------------------------------------
  // Parse the 'fields' param.
  // -----------------------------------------------------------------------
  private static function parse_fields(array $params, array $field_map, array $default_fields): array {
    if (empty($params['fields'])) return $default_fields;
    $requested = json_decode($params['fields'], true);
    if (!is_array($requested)) throw new ApiQueryException("'fields' must be a JSON array");
    foreach ($requested as $name) {
      if (!isset($field_map[$name])) {
        throw new ApiQueryException("Unknown field '$name'");
      }
    }
    return $requested;
  }

  // -----------------------------------------------------------------------
  // Parse the 'filters' param — all clauses AND-combined.
  // Each filter is [field, operator, value].
  // -----------------------------------------------------------------------
  private static function parse_filters(array $params, array $field_map): array {
    if (empty($params['filters'])) return [];
    $filters = json_decode($params['filters'], true);
    if (!is_array($filters)) throw new ApiQueryException("'filters' must be a JSON array");
    $clauses = [];
    foreach ($filters as $i => $filter) {
      $clauses[] = self::build_filter_clause($filter, $i, $field_map, 'filter');
    }
    return $clauses;
  }

  // -----------------------------------------------------------------------
  // Parse the 'or_filters' param — all clauses OR-combined, returned as
  // a single parenthesised SQL expression (or '' if param is absent).
  // -----------------------------------------------------------------------
  private static function parse_or_filters(array $params, array $field_map): string {
    if (empty($params['or_filters'])) return '';
    $filters = json_decode($params['or_filters'], true);
    if (!is_array($filters)) throw new ApiQueryException("'or_filters' must be a JSON array");
    $clauses = [];
    foreach ($filters as $i => $filter) {
      $clauses[] = self::build_filter_clause($filter, $i, $field_map, 'or_filter');
    }
    if (empty($clauses)) return '';
    return '(' . implode(' OR ', $clauses) . ')';
  }

  // -----------------------------------------------------------------------
  // Shared per-filter clause builder used by both parse_filters and
  // parse_or_filters.
  // -----------------------------------------------------------------------
  private static function build_filter_clause(mixed $filter, int $i, array $field_map, string $ctx): string {
    if (!is_array($filter) || count($filter) !== 3) {
      throw new ApiQueryException(ucfirst($ctx) . " #$i must be a 3-element array [field, operator, value]");
    }
    [$field, $op, $value] = $filter;
    $op = strtolower(trim($op));

    if (!isset($field_map[$field])) {
      throw new ApiQueryException("Unknown field '$field' in $ctx #$i");
    }
    if (empty($field_map[$field]['filterable'])) {
      throw new ApiQueryException("Field '$field' is not filterable");
    }

    $operators = self::operators();
    if (!isset($operators[$op])) {
      $allowed = implode(', ', array_keys($operators));
      throw new ApiQueryException("Unknown operator '$op' in $ctx #$i. Allowed: $allowed");
    }

    $col  = $field_map[$field]['col'];
    $type = $field_map[$field]['type'] ?? 'string';
    $def  = $operators[$op];

    if (!empty($def['ignores_value'])) {
      return $def['build']($col, null);
    }
    if ($def['scalar']) {
      $value = self::cast_value($value, $type, $field, $i);
      return $def['build']($col, db_input((string)$value));
    }
    // Array value (in / not in / between) — builder escapes internally
    return $def['build']($col, $value);
  }

  // -----------------------------------------------------------------------
  // Parse the 'order_by' param.
  // -----------------------------------------------------------------------
  private static function parse_order_by(array $params, array $field_map): string {
    if (empty($params['order_by'])) return '';
    $raw   = trim($params['order_by']);
    $parts = preg_split('/\s+/', $raw, 2);
    $field = $parts[0];
    $dir   = strtolower($parts[1] ?? 'asc');

    if (!isset($field_map[$field])) {
      throw new ApiQueryException("Unknown field '$field' in order_by");
    }
    if (empty($field_map[$field]['sortable'])) {
      throw new ApiQueryException("Field '$field' is not sortable");
    }
    if (!in_array($dir, ['asc', 'desc'], true)) {
      throw new ApiQueryException("order_by direction must be 'asc' or 'desc'");
    }
    return 'ORDER BY ' . $field_map[$field]['col'] . ' ' . strtoupper($dir);
  }

  // -----------------------------------------------------------------------
  // Light type-casting for scalar filter values.
  // -----------------------------------------------------------------------
  private static function cast_value(mixed $value, string $type, string $field, int $filter_idx): mixed {
    switch ($type) {
      case 'int':
        if (!is_numeric($value)) {
          throw new ApiQueryException("Filter #$filter_idx field '$field' expects an integer value");
        }
        return (int)$value;
      case 'float':
        if (!is_numeric($value)) {
          throw new ApiQueryException("Filter #$filter_idx field '$field' expects a numeric value");
        }
        return (float)$value;
      case 'date':
        // Accept YYYY-MM-DD; reject obviously wrong formats to avoid silent SQL issues
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value)) {
          throw new ApiQueryException("Filter #$filter_idx field '$field' expects a date in YYYY-MM-DD format");
        }
        return (string)$value;
      default:
        return (string)$value;
    }
  }
}
