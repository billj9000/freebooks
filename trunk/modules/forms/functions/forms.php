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
//  Path: /modules/forms/functions/forms.php
//

/**
 * Lookup tables the Formatter needs for the hints that resolve an id to a name.
 *
 * PhreeForm did these with per-module `pf_process_*` hooks that queried inside
 * the formatting call. The Formatter takes them as data instead, because a
 * renderer that can query is a renderer coupled to the data layer (design §2.1)
 * — so the arrays are gathered here, above the seam, and handed down.
 *
 * An absent label is OMITTED rather than blanked, so an unknown code renders as
 * itself. That matches the legacy hooks' isset() fallbacks: a report showing a
 * raw account type is recoverable, one showing an empty cell is not.
 *
 * @return array<string,array<string,string>>
 */
function forms_formatter_lookups(): array
{
    require(DIR_FS_MODULES . 'phreebooks/defaults.php');   // $coa_types_list
    $arrLocales = $GLOBALS['arrLocales'] ?? [];            // phreeform/config_phreeform.php

    $pluck = function (array $rows, string $field): array {
        $out = [];
        foreach ($rows as $key => $row) {
            $value = (string)($row[$field] ?? '');
            if ($value !== '') $out[$key] = $value;
        }
        return $out;
    };

    return [
        'coa_type'  => $pluck($coa_types_list ?? [], 'text'),
        'cvt_iso2'  => $pluck($arrLocales, 'iso2'),
        'cvt_cntry' => $pluck($arrLocales, 'name'),
    ];
}

/**
 * The document-wide values a report's page furniture is drawn from.
 *
 * PhreeForm read COMPANY_NAME straight from a global inside its render loop.
 * Passing it explicitly is what lets a renderer be tested without booting the
 * application (§4), and it is the reason the company name appears on a report
 * at all: TablePdfRenderer draws the heading band from `context`, so a Document
 * built without one prints a blank line where the company should be.
 *
 * A name that is not defined is left OUT rather than blanked, so the renderer's
 * own missing-value handling decides what to draw.
 */
function forms_report_context(): \FreeBooks\Forms\Document\Context
{
    $values = array();
    foreach (array('COMPANY_NAME', 'COMPANY_ADDRESS1', 'COMPANY_ADDRESS2', 'COMPANY_CITY_TOWN',
                   'COMPANY_STATE_PROVINCE', 'COMPANY_POSTAL_CODE', 'COMPANY_COUNTRY',
                   'COMPANY_TELEPHONE1', 'COMPANY_EMAIL') as $name) {
        if (defined($name) && (string)constant($name) !== '') {
            $values[$name] = \FreeBooks\Forms\Document\TypedValue::text((string)constant($name));
        }
    }
    return new \FreeBooks\Forms\Document\Context($values, $_SESSION['language'] ?? 'en_us');
}

/**
 * The extra recipients, as validate_send_mail() wants them.
 *
 * That function takes To, subject and body as arguments but reads CC and BCC out
 * of its `$block` array — a shape nobody would guess, and getting a key wrong
 * drops a recipient silently rather than failing. It is built in one place, and
 * tested in one place, for exactly that reason.
 *
 * Empty entries are OMITTED rather than passed as '': PHPMailer's addCC('')
 * raises an invalid-address error and the whole message fails to send, so a user
 * who left CC blank would get no email at all.
 *
 * @param array<string,string> $fields cc_email, cc_name, bcc_email, bcc_name
 * @return array<string,string>
 */
function forms_email_block(array $fields): array
{
    $block = array();
    foreach (array('CC' => 'cc', 'BCC' => 'bcc') as $key => $prefix) {
        $address = trim((string)($fields[$prefix . '_email'] ?? ''));
        if ($address === '') continue;
        $block['EMAIL_' . $key . '_ADDRESS'] = $address;
        $name = trim((string)($fields[$prefix . '_name'] ?? ''));
        if ($name !== '') $block['EMAIL_' . $key . '_NAME'] = $name;
    }
    return $block;
}

/**
 * Who the message is from, defaulting to the signed-in user.
 *
 * PhreeForm offers this and it matters: a report emailed by the bookkeeper
 * should come back to the bookkeeper, not to the company's generic address.
 *
 * @return array{0: string, 1: string} name, address
 */
function forms_email_sender(string $address = '', string $name = ''): array
{
    $address = trim($address) !== '' ? trim($address) : (string)($_SESSION['admin_email'] ?? '');
    $name    = trim($name)    !== '' ? trim($name)    : (string)($_SESSION['display_name'] ?? '');
    return array($name, $address);
}

/**
 * The binding names a form can actually supply.
 *
 * A query-driven form has a dataset, and the dataset declares its fields. The
 * nine served by a provider declare NOTHING — design §13.8 risk 3 — so the
 * provider is asked the only way it can be: the form is run once and the keys
 * are read back off the Record it produced. Measured rather than assumed, and
 * it is the same contract the renderer will resolve against.
 *
 * Shared by the canvas page and by the render endpoint deliberately. They must
 * agree on what an author may name, or the picture would accept an edit the
 * save then refuses.
 *
 * The template's own bindings are always allowed, whatever the answer, or a
 * form would lose a field the moment its record table went empty.
 *
 * @return array<string,true>
 */
function forms_binding_names($definition, $runner, $catalog, $db): array
{
    $allowed = array();

    foreach ($definition->template?->elements ?? array() as $element) {
        if (($element instanceof \FreeBooks\Forms\Document\Field
             || $element instanceof \FreeBooks\Forms\Document\Barcode)
            && $element->bind->scope !== \FreeBooks\Forms\Document\Scope::Context) {
            $allowed[$element->bind->name] = true;
        }
        if ($element instanceof \FreeBooks\Forms\Document\Image
            && $element->src instanceof \FreeBooks\Forms\Document\Binding) {
            $allowed[$element->src->name] = true;
        }
        if ($element instanceof \FreeBooks\Forms\Document\Table) {
            foreach ($element->columns as $column) $allowed[$column->bind->name] = true;
        }
        // A block's parts, which used to be missing from this list and did not
        // show it: parts were carried past the canvas untouched, so a name it
        // could not offer was a name nothing asked it about. With the parts
        // editable (§13.30) the omission became visible at once — the grid had
        // no entry to select for a line that was bound perfectly well.
        //
        // Context parts stay out, here as everywhere: they are the COMPANY's
        // details rather than the record's, nothing may be re-pointed at one,
        // and TemplateForm keeps the ones an element already has by identity.
        if ($element instanceof \FreeBooks\Forms\Document\FieldBlock) {
            foreach ($element->parts as $part) {
                if ($part->bind->scope !== \FreeBooks\Forms\Document\Scope::Context) {
                    $allowed[$part->bind->name] = true;
                }
            }
        }
    }

    if ($definition->datasetId !== null && $catalog->has($definition->datasetId)) {
        foreach (array_keys($catalog->get($definition->datasetId)->fields) as $key) $allowed[$key] = true;
        return $allowed;
    }

    $plan = $runner->planDefinition($definition);
    if (!$plan->runnable() || $plan->recordTable === ''
        || !preg_match('/^[a-z_][a-z0-9_]*$/i', $plan->recordKey)) {
        return $allowed;
    }

    $rows = $db->Execute("select distinct `" . $plan->recordKey . "` from `"
        . (defined('DB_PREFIX') ? DB_PREFIX : '') . $plan->recordTable . "`
        order by `" . $plan->recordKey . "` desc limit 5");
    while ($rows && !$rows->EOF) {
        $id = (string)$rows->fields[$plan->recordKey];
        $rows->MoveNext();
        try {
            $document = $runner->runDefinition($definition, array($id),
                array('dates' => $definition->dateDefault));
        } catch (\Throwable $e) { continue; }
        if (!($document instanceof \FreeBooks\Forms\Document\LayoutDocument)
            || count($document->records) === 0) continue;

        $record = $document->records[0];
        foreach (array_keys($record->values) as $key) $allowed[$key] = true;
        foreach ($record->tables as $lines) {
            foreach ($lines as $line) foreach (array_keys($line) as $key) $allowed[$key] = true;
            break;
        }
        break;
    }
    return $allowed;
}

/**
 * The fonts a document may be drawn in.
 *
 * PhreeForm's own list, from its defaults.php — helvetica, courier, times and,
 * on TCPDF, freeserif. It is short because these are the faces the PDF layer
 * can be relied on to have; a name TCPDF cannot find is not a fallback but an
 * error, raised from inside the renderer with the document half built.
 *
 * The keys are what a definition stores and the values are what a person reads.
 * They are not the same thing, which is the whole reason the canvas offers a
 * list: nobody would guess that the default face is spelled "freeserif".
 *
 * @return array<string,string> stored name => label
 */
function forms_font_names(): array
{
    $fonts = $GLOBALS['Fonts'] ?? array();
    return is_array($fonts) ? $fonts : array();
}

/**
 * The folder names the catalogue already carries.
 *
 * The document list used to sort by `doc_group` and print it — `cust:inv`,
 * `vend:po`, `bnk:chk` — which is an internal code, so the page looked
 * unordered when in fact it was ordered by something nobody can read.
 *
 * The names exist, in the same table, as rows with `doc_type = '0'` that the
 * list deliberately excludes. There are three kinds of them:
 *
 *   - `doc_ext = '0'`  a category:      `cust` => "Customers"
 *   - `doc_ext = 'fr'` its reports:     `cust` => "Reports"
 *   - `doc_ext = 'ff'` a forms folder:  `cust:inv` => "Invoices/Packing Slips"
 *
 * A REPORT is filed under its category, because every report in a category
 * shares one folder called "Reports" and a page full of headers reading
 * "Reports" would say nothing. A FORM is filed under its own folder, whose name
 * already identifies it. That asymmetry is in the data, not invented here.
 *
 * @return array{category: array<string,string>, form: array<string,string>}
 *   both keyed by doc_group
 */
function forms_folder_titles($db): array
{
    $titles = array('category' => array(), 'form' => array());
    $result = $db->Execute("select doc_group, doc_ext, doc_title from " . TABLE_PHREEFORM . "
      where doc_type = '0' and doc_ext in ('0', 'ff')");
    while ($result && !$result->EOF) {
        $group = (string)$result->fields['doc_group'];
        $where = $result->fields['doc_ext'] === '0' ? 'category' : 'form';
        $titles[$where][$group] = (string)$result->fields['doc_title'];
        $result->MoveNext();
    }
    return $titles;
}

/**
 * Which folder heading a document belongs under.
 *
 * Falls back to the raw group when a folder row is missing — a company can have
 * documents in a group nobody named, and a heading reading `cust:inv` is poor
 * but truthful, where dropping the row would hide a document completely.
 *
 * @param array{category: array<string,string>, form: array<string,string>} $titles
 */
function forms_folder_for(string $group, bool $isForm, array $titles): string
{
    if ($isForm) return $titles['form'][$group] ?? $group;
    // A report's group IS its category; a form's is the category plus a folder.
    return $titles['category'][$group] ?? $titles['category'][explode(':', $group)[0]] ?? $group;
}

/**
 * Render one form to PDF bytes, with nothing web-shaped around it.
 *
 * For a caller that needs the bytes in-process — an emailed attachment built
 * by a cron script, a payment-link email — rather than a browser response.
 * `pages/print/pre_process.php` does the same sequence for a page; this is it
 * without the HTTP delivery, the record selector, or a $messageStack.
 *
 * Reads whichever format the definition is stored in, through the same
 * format-aware loader the print page uses. That is the whole point of it:
 * `get_report_details()` (phreeform/functions/phreeform.php) refuses a native
 * definition outright, adding a page message and returning false — reasonable
 * for a page, fatal for a caller with no page to read it from. Before this, a
 * cron script or an ajax handler calling that function would find its report
 * simply stopped rendering, silently, the first time someone saved the same
 * form in the Forms editor (design §13.7 step 6, the render-to-string trap).
 *
 * Not for a report (`type=rpt`) or a serial/receipt form: a report is
 * DocumentRunner's, and Forms has no renderer for character-cell text at all
 * (§13.16) — planDefinition() on either would report itself unrunnable, which
 * this treats as failure like anything else.
 *
 * @param list<int> $recordIds  usually one id — the journal entry, the work
 *   order — matching what the definition's provider or dataset key expects.
 * @param array<string,mixed> $params  passed through to FormRunner::runDefinition(),
 *   e.g. ['dates' => '...'] for the statement forms; omit for a single record.
 * @return array{pdf:string,filename:string}|null  null on any failure. There is
 *   no page to put a message on, so the reason goes to error_log() instead.
 */
function forms_render_to_string(int $definitionId, array $recordIds, array $params = []): ?array
{
    global $db, $currencies;

    try {
        require_once(DIR_FS_MODULES . 'forms/classes/app/load.php');
        // Defined unconditionally at the top of forms/config.php — but a
        // caller outside the normal page bootstrap (the ZenCart cron script
        // builds its own constant list rather than loading module config.php
        // files at all) may never have required that file.
        if (!defined('TABLE_FORMS_DATASETS')) {
            define('TABLE_FORMS_DATASETS', DB_PREFIX . 'forms_datasets');
        }

        $catalog = new \FreeBooks\Forms\Catalog\Catalog();
        \FreeBooks\Forms\Catalog\ShippedDatasets::registerAll($catalog);
        (new \FreeBooks\Forms\Catalog\CustomDatasetStore(
            $db, new \FreeBooks\Forms\Catalog\SchemaInspector($db), TABLE_FORMS_DATASETS
        ))->registerAll($catalog);

        $definition = (new \FreeBooks\Forms\Definition\DefinitionLoader($catalog))->load($definitionId);
        $document   = (new \FreeBooks\Forms\App\FormRunner($db, $catalog))
            ->runDefinition($definition, $recordIds, $params);

        if (count($document->records ?? []) === 0) {
            error_log("forms_render_to_string: pf_$definitionId produced no records for "
                . implode(',', $recordIds));
            return null;
        }

        // A receipt is TEXT for a till printer, not a PDF (§13.27). Returned
        // under the same 'pdf' key the callers already read, because what they
        // do with it is identical — the POS handlers put it straight into an
        // ajax response, and the key is a name, not a promise about the format.
        if ($document instanceof \FreeBooks\Forms\Document\SerialDocument) {
            $receipt = (new \FreeBooks\Forms\Render\SerialRenderer(
                new \FreeBooks\Forms\Render\Formatter(
                    currencies:      $currencies,
                    defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
                    lookups:         forms_formatter_lookups(),
                )
            ))->render($document);
            return ['pdf' => $receipt->bytes, 'filename' => $receipt->filename];
        }

        if (!$document instanceof \FreeBooks\Forms\Document\LayoutDocument) {
            error_log("forms_render_to_string: pf_$definitionId is not a form.");
            return null;
        }

        $renderer = new \FreeBooks\Forms\Render\LayoutPdfRenderer(
            formatter: new \FreeBooks\Forms\Render\Formatter(
                currencies:      $currencies,
                defaultCurrency: defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
                lookups:         forms_formatter_lookups(),
            ),
            defaultFont:          defined('PDF_DEFAULT_FONT')    ? PDF_DEFAULT_FONT          : 'helvetica',
            rowSpacing:           defined('PF_DEFAULT_ROWSPACE') ? (float)PF_DEFAULT_ROWSPACE : 2.0,
            continuedLabel:       defined('TEXT_CONTINUED')      ? TEXT_CONTINUED            : 'Continued',
            pageOfLabel:          defined('TEXT_OF')             ? TEXT_OF                   : 'of',
            imageDirectory:       PF_DIR_MY_REPORTS . 'images',
            linkedImageDirectory: DIR_FS_MY_FILES . $_SESSION['company'] . '/',
        );

        $output = $renderer->render($document, new \FreeBooks\Forms\Document\RenderOptions());
        return ['pdf' => $output->bytes, 'filename' => $output->filename];
    } catch (\Throwable $e) {
        error_log("forms_render_to_string: pf_$definitionId failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Act on a New, Copy, Rename or Delete asked for by one of the two lists.
 *
 * Shared because the two lists differ only in what they hold: the Reports page
 * lists reports and Form Layout lists forms, and a document is created,
 * copied, renamed and deleted the same way whichever it is (§13.29). Writing it
 * twice is how the guards come to be on one list and not the other.
 *
 * Answers with the id to open, or 0. Every refusal goes to the message stack —
 * these are things a person asked for, so a person has to be told why not.
 *
 * @param object $store   a DocumentStore
 * @param string $type    Definition::REPORT or Definition::FORM, for a new one
 * @return int the document to open next, or 0 to stay on the list
 */
function forms_document_action($store, string $type, int $securityLevel): int
{
    global $messageStack;

    $action = (string)($_REQUEST['action'] ?? '');
    if (!in_array($action, array('new', 'copy', 'rename', 'delete'), true)) return 0;

    // Level 3 writes a document, level 4 removes one. Deleting is the only
    // action here that cannot be undone from inside Forms, which is the whole
    // reason it asks for more.
    $needed = $action === 'delete' ? 4 : 3;
    if ($securityLevel < $needed) {
        $messageStack->add(FORMS_DESIGN_DENIED, 'error');
        return 0;
    }

    $id    = isset($_REQUEST['rID']) ? (int)$_REQUEST['rID'] : 0;
    $title = trim((string)($_REQUEST['doc_title'] ?? ''));

    try {
        switch ($action) {
            case 'new':
                $made = $store->create($type, $title,
                    (string)($_REQUEST['doc_group'] ?? ''),
                    ($_REQUEST['dataset'] ?? '') !== '' ? (string)$_REQUEST['dataset'] : null);
                $messageStack->add(sprintf(FORMS_DOC_CREATED, $title), 'success');
                return $made;

            case 'copy':
                $made = $store->copy($id, $title);
                $messageStack->add(sprintf(FORMS_DOC_COPIED, $title), 'success');
                return $made;

            case 'rename':
                $store->rename($id, $title);
                $messageStack->add(sprintf(FORMS_DOC_RENAMED, $title), 'success');
                return 0;

            case 'delete':
                // Read BEFORE it goes: afterwards there is no row to ask.
                $gone = $store->isLastInGroup($id);
                $store->delete($id);
                $messageStack->add(FORMS_DOC_DELETED, 'success');
                if ($gone) $messageStack->add(FORMS_DOC_WAS_LAST, 'caution');
                return 0;
        }
    } catch (\Throwable $e) {
        $messageStack->add($e->getMessage(), 'error');
    }
    return 0;
}

/**
 * The script behind New, Copy and Delete on the two document lists.
 *
 * Its own `<script>` block, emitted AFTER whatever the page's own js_include
 * contains, and that is the whole point of it being here rather than pasted
 * into each page: the canvas's script is `type="module"`, and a function
 * declared inside a module is scoped to that module. An `onclick="formsDocNew()"`
 * on a page whose script is a module finds nothing at all — which is exactly
 * what happened, and it fails silently, because a handler naming an undefined
 * function is only an error when it is clicked.
 *
 * Listeners are DELEGATED from the document rather than written as inline
 * handlers, so nothing has to be global and a title never has to survive being
 * JSON-encoded inside an HTML attribute — it travels as a data attribute, which
 * needs escaping once and in one obvious way.
 */
function forms_document_actions_script(): void
{
?>
<script type="text/javascript">
(function () {
  function submitAction(kind, id, title) {
    var form = document.getElementById('forms_doc_action');
    if (!form) return;
    document.getElementById('forms_doc_action_kind').value  = kind;
    document.getElementById('forms_doc_action_id').value    = id;
    document.getElementById('forms_doc_action_title').value = title || '';
    form.submit();
  }

  document.addEventListener('click', function (event) {
    var el = event.target.closest('[data-fb-doc]');
    if (!el) return;
    event.preventDefault();

    var kind  = el.getAttribute('data-fb-doc');
    var id    = el.getAttribute('data-id') || '';
    var title = el.getAttribute('data-title') || '';

    if (kind === 'new') {
      var modal = document.getElementById('forms_new_modal');
      if (!modal || typeof bootstrap === 'undefined') return;
      bootstrap.Modal.getOrCreateInstance(modal).show();
      var name = document.getElementById('forms_new_name');
      if (name) modal.addEventListener('shown.bs.modal',
          function () { name.focus(); }, { once: true });
      return;
    }

    if (kind === 'copy') {
      var proposed = <?php echo json_encode(FORMS_DOC_COPY_OF); ?>.replace('%s', title);
      var chosen   = window.prompt(<?php echo json_encode(FORMS_DOC_NAME); ?>, proposed);
      if (chosen === null) return;
      submitAction('copy', id, chosen);
      return;
    }

    if (kind === 'delete') {
      // `last` is worked out on the SERVER while the row still exists — after
      // the delete there is nothing left to ask, and what it warns about is a
      // Print button on another page entirely.
      var question = el.getAttribute('data-last') === '1'
          ? <?php echo json_encode(FORMS_DOC_CONFIRM_LAST); ?>
          : <?php echo json_encode(FORMS_DOC_CONFIRM_DELETE); ?>;
      if (!window.confirm(question)) return;
      submitAction('delete', id, '');
    }
  });
})();
</script>
<?php
}
