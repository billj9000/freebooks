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
//  Path: /modules/forms/pages/design/template_main.php
//
$toolbar->icon_list['open']['show']   = false;
$toolbar->icon_list['delete']['show'] = false;
$toolbar->icon_list['print']['show']  = false;
$toolbar->icon_list['save']['show']   = $forms_design_definition !== null && $security_level > 2;
$toolbar->icon_list['save']['params'] = 'onclick="document.getElementById(\'forms_design_form\').submit()"';
// Back to the Reports list, which is the only list of reports there is. The
// canvas returns to ITS list, and that is not inconsistent: forms are kept off
// the Reports page, so the canvas's chooser is the only list a form appears on.
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \''
    . html_href_link(FILENAME_DEFAULT, 'module=forms&page=main', 'SSL') . '\'"';
$toolbar->add_help('11.06');
echo $toolbar->build_toolbar();

if ($forms_design_definition === null) { ?>
<h1><?php echo FORMS_DESIGN_TITLE; ?></h1>
<p class="text-muted"><?php echo FORMS_DESIGN_PICK_ONE; ?></p>
<p><a class="btn btn-primary btn-sm" href="<?php
  echo html_href_link(FILENAME_DEFAULT, 'module=forms&amp;page=main', 'SSL'); ?>">
  <?php echo FORMS_DESIGN_BACK_TO_LIST; ?></a></p>
<?php
  return;
}

$values  = $forms_design_values;
$columns = array_values((array)($values['column'] ?? array()));
$sorts   = array_values((array)($values['sort']   ?? array()));
$filters = array_values((array)($values['filter'] ?? array()));
$style   = (array)($values['style'] ?? array());
$bands   = (array)($style['band'] ?? array());
?>
<h1><?php echo htmlspecialchars($forms_design_definition->title); ?></h1>

<?php if ($forms_design_was_legacy) { ?>
<div class="alert alert-warning py-2 px-3 small">
  <?php echo FORMS_DESIGN_CONVERT_WARNING; ?>
</div>
<?php } ?>

<?php echo html_form('forms_design_form', FILENAME_DEFAULT,
    'module=forms&page=design&action=save&rID=' . (int)$forms_design_id); ?>

<div class="row g-3">
  <div class="col-xl-7">

    <div class="card fb-card mb-3">
      <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DESIGN_ABOUT; ?></div>
      <div class="card-body py-2">
        <div class="row g-2">
          <div class="col-md-5">
            <label class="form-label mb-1" for="title"><?php echo FORMS_DESIGN_NAME; ?></label>
            <input type="text" class="form-control form-control-sm" id="title" name="title"
                   value="<?php echo htmlspecialchars((string)($values['title'] ?? '')); ?>" required>
          </div>
          <div class="col-md-7">
            <label class="form-label mb-1" for="description"><?php echo FORMS_DESIGN_DESCRIPTION; ?></label>
            <input type="text" class="form-control form-control-sm" id="description" name="description"
                   value="<?php echo htmlspecialchars((string)($values['description'] ?? '')); ?>">
          </div>
        </div>
        <p class="text-muted small mb-0 mt-2">
          <?php echo $forms_design_dataset !== null
              ? sprintf(FORMS_DESIGN_FROM_DATASET, htmlspecialchars($forms_design_dataset->label),
                        htmlspecialchars($forms_design_dataset->id))
              : sprintf(FORMS_DESIGN_FROM_PROVIDER, htmlspecialchars((string)$forms_design_definition->provider)); ?>
        </p>
      </div>
    </div>

    <div class="card fb-card mb-3">
      <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DESIGN_COLUMNS; ?></div>
      <div class="card-body p-0">
        <table class="table table-borderless table-hover table-sm mb-0 align-middle">
          <thead class="table-dark">
            <tr>
              <th style="width:26%"><?php echo FORMS_DESIGN_COL_FIELD; ?></th>
              <th style="width:24%"><?php echo FORMS_DESIGN_COL_HEADING; ?></th>
              <th style="width:9%"><?php echo FORMS_DESIGN_COL_WIDTH; ?></th>
              <th style="width:13%"><?php echo FORMS_DESIGN_COL_ALIGN; ?></th>
              <th style="width:11%"><?php echo FORMS_DESIGN_COL_FORMAT; ?></th>
              <th style="width:7%" class="text-center"><?php echo FORMS_DESIGN_COL_TOTAL; ?></th>
              <th style="width:7%" class="text-center"><?php echo FORMS_DESIGN_COL_BREAK; ?></th>
              <th style="width:8%" class="text-end">&nbsp;</th>
            </tr>
          </thead>
          <tbody id="forms_design_columns">
<?php foreach ($columns as $i => $column) { ?>
            <tr class="<?php echo ($i % 2) ? 'odd' : 'even'; ?>">
              <td><?php echo html_pull_down_menu("column[$i][key]", $forms_design_fields,
                    (string)($column['key'] ?? ''), 'class="form-select form-select-sm"'); ?></td>
              <td><input type="text" class="form-control form-control-sm" name="column[<?php echo $i; ?>][heading]"
                         value="<?php echo htmlspecialchars((string)($column['heading'] ?? '')); ?>"></td>
              <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end"
                         name="column[<?php echo $i; ?>][width]"
                         value="<?php echo htmlspecialchars((string)($column['width'] ?? '')); ?>"></td>
              <td><?php echo html_pull_down_menu("column[$i][align]", $forms_design_aligns,
                    (string)($column['align'] ?? ''), 'class="form-select form-select-sm"'); ?></td>
              <td><?php echo forms_format_select(
                    'class="form-select form-select-sm" name="column[' . $i . '][format]"',
                    $forms_design_formats, (string)($column['format'] ?? '')); ?></td>
              <td class="text-center"><input type="checkbox" class="form-check-input"
                         name="column[<?php echo $i; ?>][total]" value="1"
                         <?php echo !empty($column['total']) ? 'checked' : ''; ?>></td>
              <?php // Ticked, the next field starts a new column; unticked, it stacks
                    // underneath this one. Nearly every report ticks every column,
                    // and an unticked box posts NOTHING, so without this control
                    // every column would collapse into one stacked band. ?>
              <td class="text-center"><input type="checkbox" class="form-check-input"
                         name="column[<?php echo $i; ?>][pageBreak]" value="1"
                         <?php echo !empty($column['pageBreak']) ? 'checked' : ''; ?>></td>
              <td class="text-end text-nowrap">
                <button type="button" class="btn btn-primary btn-sm" onclick="formsDesignMove(this,-1)">&uarr;</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="formsDesignMove(this,1)">&darr;</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="formsDesignRemove(this)">&times;</button>
              </td>
            </tr>
<?php } ?>
          </tbody>
        </table>
        <div class="p-2">
          <button type="button" class="btn btn-primary btn-sm" onclick="formsDesignAddColumn()">
            <?php echo FORMS_DESIGN_ADD_COLUMN; ?>
          </button>
          <span class="text-muted small ms-2"><?php echo FORMS_DESIGN_COLUMNS_NOTE; ?></span>
        </div>
      </div>
    </div>

    <div class="card fb-card mb-3">
      <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DESIGN_GROUPING; ?></div>
      <div class="card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-5">
            <label class="form-label mb-1" for="group_by"><?php echo FORMS_DESIGN_GROUP_BY; ?></label>
            <?php echo html_pull_down_menu('group[by]',
                array_merge(array(array('id' => '', 'text' => FORMS_DESIGN_GROUP_NONE)), $forms_design_fields),
                (string)($values['group']['by'] ?? ''), 'class="form-select form-select-sm"'); ?>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1" for="group_format"><?php echo FORMS_DESIGN_GROUP_FORMAT; ?></label>
            <?php echo forms_format_select(
                  'class="form-select form-select-sm" id="group_format" name="group[format]"',
                  $forms_design_formats, (string)($values['group']['format'] ?? '')); ?>
          </div>
          <div class="col-md-4">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="totalsOnly" name="totalsOnly" value="1"
                     <?php echo !empty($values['totalsOnly']) ? 'checked' : ''; ?>>
              <label class="form-check-label" for="totalsOnly"><?php echo FORMS_DESIGN_TOTALS_ONLY; ?></label>
            </div>
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="groupPageBreak"
                     name="style[groupPageBreak]" value="1"
                     <?php echo !empty($style['groupPageBreak']) ? 'checked' : ''; ?>>
              <label class="form-check-label" for="groupPageBreak"><?php echo FORMS_DESIGN_GROUP_BREAK; ?></label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card fb-card mb-3">
      <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DESIGN_SORTING; ?></div>
      <div class="card-body p-0">
        <table class="table table-borderless table-sm mb-0 align-middle">
          <tbody id="forms_design_sorts">
<?php foreach ($sorts as $i => $sort) { ?>
            <tr class="<?php echo ($i % 2) ? 'odd' : 'even'; ?>">
              <td style="width:55%"><?php echo html_pull_down_menu("sort[$i][field]", $forms_design_sortable,
                    (string)($sort['field'] ?? ''), 'class="form-select form-select-sm"'); ?></td>
              <td style="width:30%"><?php echo html_pull_down_menu("sort[$i][direction]",
                    array(array('id' => 'ASC',  'text' => FORMS_DESIGN_ASCENDING),
                          array('id' => 'DESC', 'text' => FORMS_DESIGN_DESCENDING)),
                    (string)($sort['direction'] ?? 'ASC'), 'class="form-select form-select-sm"'); ?></td>
              <td class="text-end">
                <button type="button" class="btn btn-primary btn-sm" onclick="formsDesignMove(this,-1)">&uarr;</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="formsDesignMove(this,1)">&darr;</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="formsDesignRemove(this)">&times;</button>
              </td>
            </tr>
<?php } ?>
          </tbody>
        </table>
        <div class="p-2">
          <button type="button" class="btn btn-primary btn-sm" onclick="formsDesignAddSort()">
            <?php echo FORMS_DESIGN_ADD_SORT; ?>
          </button>
        </div>
      </div>
    </div>

    <div class="card fb-card mb-3">
      <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DESIGN_FILTERS; ?></div>
      <div class="card-body p-0">
        <table class="table table-borderless table-sm mb-0 align-middle">
          <thead class="table-dark">
            <tr>
              <th style="width:30%"><?php echo FORMS_DESIGN_COL_FIELD; ?></th>
              <th style="width:22%"><?php echo FORMS_DESIGN_FILTER_IS; ?></th>
              <th style="width:20%"><?php echo FORMS_DESIGN_FILTER_VALUE; ?></th>
              <th style="width:18%"><?php echo FORMS_DESIGN_FILTER_AND; ?></th>
              <th class="text-end">&nbsp;</th>
            </tr>
          </thead>
          <tbody id="forms_design_filters">
<?php foreach ($filters as $i => $filter) {
        $expression = (string)($filter['expression'] ?? ''); ?>
            <tr class="<?php echo ($i % 2) ? 'odd' : 'even'; ?>">
              <td><?php echo html_pull_down_menu("filter[$i][field]", $forms_design_fields,
                    (string)($filter['field'] ?? ''), 'class="form-select form-select-sm"'); ?></td>
              <td><?php echo html_pull_down_menu("filter[$i][operator]", $forms_design_operators,
                    (string)($filter['operator'] ?? ''), 'class="form-select form-select-sm"'); ?></td>
<?php   if ($expression !== '') { ?>
              <td colspan="2" class="text-muted small">
                <?php echo FORMS_DESIGN_FILTER_EXPRESSION; ?>
                <input type="hidden" name="filter[<?php echo $i; ?>][expression]"
                       value="<?php echo htmlspecialchars($expression); ?>">
              </td>
<?php   } else { ?>
              <td><input type="text" class="form-control form-control-sm" name="filter[<?php echo $i; ?>][value1]"
                         value="<?php echo htmlspecialchars((string)($filter['value1'] ?? '')); ?>"></td>
              <td><input type="text" class="form-control form-control-sm" name="filter[<?php echo $i; ?>][value2]"
                         value="<?php echo htmlspecialchars((string)($filter['value2'] ?? '')); ?>"></td>
<?php   } ?>
              <td class="text-end">
                <button type="button" class="btn btn-primary btn-sm" onclick="formsDesignRemove(this)">&times;</button>
              </td>
            </tr>
<?php } ?>
          </tbody>
        </table>
        <div class="p-2">
          <button type="button" class="btn btn-primary btn-sm" onclick="formsDesignAddFilter()">
            <?php echo FORMS_DESIGN_ADD_FILTER; ?>
          </button>
          <span class="text-muted small ms-2"><?php echo FORMS_DESIGN_FILTERS_NOTE; ?></span>
        </div>
      </div>
    </div>

    <div class="card fb-card mb-3">
      <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DESIGN_PAGE; ?></div>
      <div class="card-body py-2">
        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label mb-1" for="paper_size"><?php echo FORMS_DESIGN_PAPER; ?></label>
            <?php echo html_pull_down_menu('paper[size]', $forms_design_papers,
                (string)($values['paper']['size'] ?? ''), 'class="form-select form-select-sm"'); ?>
          </div>
          <div class="col-md-3">
            <label class="form-label mb-1" for="paper_orientation"><?php echo FORMS_DESIGN_ORIENTATION; ?></label>
            <?php echo html_pull_down_menu('paper[orientation]', $forms_design_orientations,
                (string)($values['paper']['orientation'] ?? 'P'), 'class="form-select form-select-sm"'); ?>
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1"><?php echo FORMS_DESIGN_MARGINS; ?></label>
            <div class="input-group input-group-sm">
<?php foreach (array('top' => FORMS_DESIGN_TOP, 'bottom' => FORMS_DESIGN_BOTTOM,
                     'left' => FORMS_DESIGN_LEFT, 'right' => FORMS_DESIGN_RIGHT) as $edge => $label) { ?>
              <span class="input-group-text"><?php echo $label; ?></span>
              <input type="text" inputmode="decimal" class="form-control text-end"
                     name="paper[margin][<?php echo $edge; ?>]"
                     value="<?php echo htmlspecialchars((string)($values['paper']['margin'][$edge] ?? '')); ?>">
<?php } ?>
            </div>
            <div class="form-text"><?php echo FORMS_DESIGN_MM; ?></div>
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1" for="dates_default"><?php echo FORMS_DESIGN_DATE_RANGE; ?></label>
            <?php echo html_pull_down_menu('dates[default]', $forms_design_dates,
                (string)($values['dates']['default'] ?? ''), 'class="form-select form-select-sm"'); ?>
          </div>
          <div class="col-md-5">
            <label class="form-label mb-1" for="dates_field"><?php echo FORMS_DESIGN_DATE_FIELD; ?></label>
            <?php echo html_pull_down_menu('dates[field]',
                array_merge(array(array('id' => '', 'text' => FORMS_DESIGN_GROUP_NONE)), $forms_design_fields),
                (string)($values['dates']['field'] ?? ''), 'class="form-select form-select-sm"'); ?>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="truncate" name="style[truncate]" value="1"
                     <?php echo !empty($style['truncate']) ? 'checked' : ''; ?>>
              <label class="form-check-label" for="truncate"><?php echo FORMS_DESIGN_TRUNCATE; ?></label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card fb-card mb-3">
      <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DESIGN_HEADINGS; ?></div>
      <div class="card-body py-2">
        <div class="row g-2 mb-2">
          <div class="col-md-6">
            <label class="form-label mb-1" for="style_title1Text"><?php echo FORMS_DESIGN_TITLE1; ?></label>
            <input type="text" class="form-control form-control-sm" id="style_title1Text"
                   name="style[title1Text]"
                   value="<?php echo htmlspecialchars((string)($style['title1Text'] ?? '')); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1" for="style_title2Text"><?php echo FORMS_DESIGN_TITLE2; ?></label>
            <input type="text" class="form-control form-control-sm" id="style_title2Text"
                   name="style[title2Text]"
                   value="<?php echo htmlspecialchars((string)($style['title2Text'] ?? '')); ?>">
          </div>
        </div>
        <div class="form-text mb-2"><?php echo FORMS_DESIGN_TITLE_TOKENS; ?></div>

        <table class="table table-borderless table-sm mb-0 align-middle">
          <thead class="table-dark">
            <tr>
              <th style="width:22%"><?php echo FORMS_DESIGN_BAND; ?></th>
              <th style="width:12%" class="text-center"><?php echo FORMS_DESIGN_SHOWN; ?></th>
              <th style="width:12%" class="text-center"><?php echo FORMS_DESIGN_STYLED; ?></th>
              <th style="width:20%"><?php echo FORMS_DESIGN_FONT; ?></th>
              <th style="width:10%"><?php echo FORMS_DESIGN_SIZE; ?></th>
              <th style="width:10%"><?php echo FORMS_DESIGN_COLOUR; ?></th>
              <th style="width:14%"><?php echo FORMS_DESIGN_COL_ALIGN; ?></th>
            </tr>
          </thead>
          <tbody>
<?php $bandRows = array(
        'heading' => array(FORMS_DESIGN_BAND_HEADING, 'showHeading'),
        'title1'  => array(FORMS_DESIGN_BAND_TITLE1,  'showTitle1'),
        'title2'  => array(FORMS_DESIGN_BAND_TITLE2,  'showTitle2'),
        'filter'  => array(FORMS_DESIGN_BAND_FILTER,  null),
        'data'    => array(FORMS_DESIGN_BAND_DATA,    null),
        'totals'  => array(FORMS_DESIGN_BAND_TOTALS,  null),
      );
      $row = 0;
      foreach ($bandRows as $name => $meta) {
        $band = (array)($bands[$name] ?? array()); ?>
            <tr class="<?php echo ($row++ % 2) ? 'odd' : 'even'; ?>">
              <td><?php echo $meta[0]; ?></td>
              <td class="text-center">
<?php   if ($meta[1] !== null) { ?>
                <input type="checkbox" class="form-check-input" name="style[<?php echo $meta[1]; ?>]" value="1"
                       <?php echo !empty($style[$meta[1]]) ? 'checked' : ''; ?>>
<?php   } else { echo '&mdash;'; } ?>
              </td>
              <td class="text-center">
                <input type="checkbox" class="form-check-input"
                       name="style[band][<?php echo $name; ?>][set]" value="1"
                       <?php echo !empty($band['set']) ? 'checked' : ''; ?>>
              </td>
              <td><input type="text" class="form-control form-control-sm"
                         name="style[band][<?php echo $name; ?>][font]"
                         value="<?php echo htmlspecialchars((string)($band['font'] ?? '')); ?>"></td>
              <td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end"
                         name="style[band][<?php echo $name; ?>][size]"
                         value="<?php echo htmlspecialchars((string)($band['size'] ?? '')); ?>"></td>
              <td><input type="color" class="form-control form-control-sm form-control-color"
                         name="style[band][<?php echo $name; ?>][colour]"
                         value="<?php echo htmlspecialchars(($band['colour'] ?? '') !== '' ? $band['colour'] : '#000000'); ?>"></td>
              <td><?php echo html_pull_down_menu("style[band][$name][align]", $forms_design_aligns,
                    (string)($band['align'] ?? ''), 'class="form-select form-select-sm"'); ?></td>
            </tr>
<?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-5">
    <div class="card fb-card mb-3 position-sticky" style="top:0.5rem">
      <div class="card-header py-1 fb-widget-header"><?php echo FORMS_DESIGN_PREVIEW; ?></div>
      <div class="card-body p-2">
        <p class="text-muted small mb-2"><?php echo FORMS_DESIGN_PREVIEW_NOTE; ?></p>
        <?php // A date range for the PREVIEW only. Deliberately not named
              // dates[default] — that is the report's own setting, and an
              // author should not have to change what gets saved in order to
              // see a row. DefinitionForm never reads this name. ?>
        <div class="mb-2">
          <label class="form-label mb-1" for="preview_dates"><?php echo FORMS_DESIGN_PREVIEW_DATES; ?></label>
          <?php echo html_pull_down_menu('preview_dates', $forms_design_preview_dates,
              (string)($values['dates']['default'] ?? 'a'), 'class="form-select form-select-sm"'); ?>
        </div>
        <?php // formaction/formtarget rather than a second form or an XHR: the
              // preview must post exactly the fields the save posts, and the
              // browser will only guarantee that if it is the same form. A PDF
              // cannot be swapped into the DOM, so it lands in an iframe. ?>
        <button type="submit" class="btn btn-primary btn-sm mb-2"
                formaction="<?php echo html_href_link(FILENAME_DEFAULT,
                    'module=forms&page=ajax&op=design_preview&rID=' . (int)$forms_design_id, 'SSL'); ?>"
                formtarget="forms_design_preview">
          <?php echo FORMS_DESIGN_REFRESH; ?>
        </button>
        <iframe id="forms_design_preview" name="forms_design_preview" title="<?php echo FORMS_DESIGN_PREVIEW; ?>"
                style="width:100%;height:70vh;border:1px solid #a6c9e2;background:#fff"></iframe>
      </div>
    </div>
  </div>
</div>
</form>
