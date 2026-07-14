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
//  Path: /modules/api/classes/resources/contacts.php
//
// Contacts resource.
//
// The join keeps one row per contact by matching only the main (mailing)
// address row — address_book.type LIKE '%m' (e.g. 'cm', 'vm', 'em').
// To retrieve customers only, filter type=c, or leave
// it open to pull all contact types.
//
// contact types:  c=customer  v=vendor  e=employee  b=branch  j=project  i=CRM
//
// 'addresses' is a virtual field resolved by post_process(): it batch-loads
// all address_book rows for the current page of contacts in a single query.
// Address type codes: cm/vm/em=main, cs/vs=ship-to, cb/vb=bill-to, etc.

class api_resource_contacts {

  public string $primary_key = 'id';

  // -----------------------------------------------------------------------
  // Field map: public API name → SQL expression + metadata
  // -----------------------------------------------------------------------
  public function field_map(): array {
    return [
      // ── master record (contacts table) ───────────────────────────────────
      'id'          => ['col' => 'c.id',             'type' => 'int',    'filterable' => true,  'sortable' => true],
      'type'        => ['col' => 'c.type',            'type' => 'string', 'filterable' => true,  'sortable' => false],
      'short_name'  => ['col' => 'c.short_name',      'type' => 'string', 'filterable' => true,  'sortable' => true],
      'first_name'  => ['col' => 'c.contact_first',   'type' => 'string', 'filterable' => true,  'sortable' => true],
      'last_name'   => ['col' => 'c.contact_last',    'type' => 'string', 'filterable' => true,  'sortable' => true],
      'inactive'    => ['col' => 'c.inactive',        'type' => 'string', 'filterable' => true,  'sortable' => false],
      'account_number' => ['col' => 'c.account_number', 'type' => 'string', 'filterable' => true, 'sortable' => true],
      'modified'    => ['col' => 'c.last_update',     'type' => 'date',   'filterable' => true,  'sortable' => true],
      // ── main address row (address_book table) ────────────────────────────
      'primary_name'  => ['col' => 'a.primary_name', 'type' => 'string', 'filterable' => true,  'sortable' => true],
      'contact'       => ['col' => 'a.contact',      'type' => 'string', 'filterable' => true,  'sortable' => false],
      'address1'      => ['col' => 'a.address1',     'type' => 'string', 'filterable' => false, 'sortable' => false],
      'address2'      => ['col' => 'a.address2',     'type' => 'string', 'filterable' => false, 'sortable' => false],
      'city'          => ['col' => 'a.city_town',    'type' => 'string', 'filterable' => true,  'sortable' => true],
      'state'         => ['col' => 'a.state_province','type' => 'string', 'filterable' => true,  'sortable' => false],
      'postal_code'   => ['col' => 'a.postal_code',  'type' => 'string', 'filterable' => true,  'sortable' => false],
      'country'       => ['col' => 'a.country_code', 'type' => 'string', 'filterable' => true,  'sortable' => false],
      'telephone1'    => ['col' => 'a.telephone1',   'type' => 'string', 'filterable' => true,  'sortable' => false],
      'telephone2'    => ['col' => 'a.telephone2',   'type' => 'string', 'filterable' => true,  'sortable' => false],
      'telephone3'    => ['col' => 'a.telephone3',   'type' => 'string', 'filterable' => true,  'sortable' => false],
      'telephone4'    => ['col' => 'a.telephone4',   'type' => 'string', 'filterable' => true,  'sortable' => false],
      'email'         => ['col' => 'a.email',        'type' => 'string', 'filterable' => true,  'sortable' => false],
      'website'       => ['col' => 'a.website',      'type' => 'string', 'filterable' => false, 'sortable' => false],
      // ── virtual: all address_book rows loaded by post_process() ─────────
      'addresses'     => ['col' => '', 'type' => 'virtual', 'filterable' => false, 'sortable' => false, 'virtual' => true],
    ];
  }

  // -----------------------------------------------------------------------
  // FROM clause — contacts LEFT JOIN address_book on main address only
  // -----------------------------------------------------------------------
  public function base_from(): string {
    return TABLE_CONTACTS    . " c"
         . " LEFT JOIN " . TABLE_ADDRESS_BOOK . " a ON c.id = a.ref_id AND a.type LIKE '%m'";
  }

  // -----------------------------------------------------------------------
  // No base WHERE — callers filter by type, inactive, etc.
  // -----------------------------------------------------------------------
  public function base_where(): string {
    return '';
  }

  // -----------------------------------------------------------------------
  // Default fields returned when ?fields= is omitted
  // -----------------------------------------------------------------------
  public function default_fields(): array {
    return ['id', 'type', 'short_name', 'primary_name', 'contact',
            'telephone1', 'telephone2', 'email', 'inactive', 'modified'];
  }

  // -----------------------------------------------------------------------
  // Post-process: batch-load all address_book rows for the current page.
  // One query regardless of page size — no N+1.
  // -----------------------------------------------------------------------
  public function post_process(array $rows, array $virtual_fields): array {
    global $db;

    if (!in_array('addresses', $virtual_fields) || empty($rows)) return $rows;

    $ids     = array_map('intval', array_column($rows, 'id'));
    $id_list = implode(',', $ids);

    $sql = "SELECT ref_id, type, primary_name, contact,
                   address1, address2, city_town, state_province,
                   postal_code, country_code,
                   telephone1, telephone2, telephone3, telephone4,
                   email, website
            FROM " . TABLE_ADDRESS_BOOK . "
            WHERE ref_id IN ($id_list)
            ORDER BY ref_id, type";

    $result = $db->Execute($sql);
    $addr_by_id = [];
    while (!$result->EOF) {
      $f   = $result->fields;
      $rid = (int)$f['ref_id'];
      $addr_by_id[$rid][] = [
        'type'         => $f['type'],
        'primary_name' => $f['primary_name'],
        'contact'      => $f['contact'],
        'address1'     => $f['address1'],
        'address2'     => $f['address2'],
        'city'         => $f['city_town'],
        'state'        => $f['state_province'],
        'postal_code'  => $f['postal_code'],
        'country'      => $f['country_code'],
        'telephone1'   => $f['telephone1'],
        'telephone2'   => $f['telephone2'],
        'telephone3'   => $f['telephone3'],
        'telephone4'   => $f['telephone4'],
        'email'        => $f['email'],
        'website'      => $f['website'],
      ];
      $result->MoveNext();
    }

    foreach ($rows as &$row) {
      $row['addresses'] = $addr_by_id[(int)$row['id']] ?? [];
    }
    unset($row);

    return $rows;
  }
}
