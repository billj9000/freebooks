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
//  Path: /modules/forms/classes/app/document_store.php
//
/**
 * Forms — creating, copying, renaming and deleting a document. See §13.29.
 *
 * The catalogue half of Forms.
 *
 * One class rather than four page actions because a document is TWO things that
 * must agree — a catalogue row and a definition file — and every operation here
 * touches both. Splitting them across the pages that offer the buttons is how
 * you get a row with no file, which lists a document that cannot be opened, or
 * a file with no row, which is invisible and never deleted.
 *
 * Everything is ordered so that a failure leaves the catalogue consistent:
 * writes create the file before the row is trusted, and delete removes the row
 * FIRST. A row without a file is a document that cannot be opened; a file
 * without a row is a document nobody can see. Of the two, the second is the one
 * you can find again — the id is in the filename.
 *
 * Sits above the seam: it reads and writes both the database and the filesystem
 * on purpose. What it hands back is an id.
 */

namespace FreeBooks\Forms\App;

use FreeBooks\Forms\Catalog\Catalog;
use FreeBooks\Forms\Definition\{Definition, DefinitionException, DefinitionLoader};
use FreeBooks\Forms\Document\{SerialTemplate, Template};

/** Refused, with a reason a person can act on. */
class DocumentStoreException extends \RuntimeException {}

final class DocumentStore
{
    /**
     * Settings that point at a document, matched by NAME rather than listed.
     *
     * A configuration row holding the id of a form is how the ZenCart VAT
     * invoice is chosen, and deleting that form leaves the setting pointing at
     * nothing — which does not fail loudly, it just stops emailing invoices
     * (§13.26). Matching by name generalises to settings not written yet.
     *
     * Anchored at the END, and that is not tidiness: a key that NAMES a
     * document ends in what it names. Matching the word anywhere would catch
     * settings that merely mention one — a boolean whose value of 1 happens to
     * equal a document id would refuse the deletion of document 1 for a reason
     * that makes no sense.
     */
    public const SETTING_NAMES = '(_FORM|_DOCUMENT|_REPORT|_RECEIPT|_INVOICE|_STATEMENT)$';

    public function __construct(
        private readonly \queryFactory   $db,
        private readonly Catalog         $catalog,
        private readonly DefinitionLoader $loader,
        private readonly string          $table,
    ) {}

    /**
     * A new, empty document.
     *
     * Empty means empty: a report with no columns and a form with no elements,
     * which both render their furniture and nothing else. That is deliberate —
     * an author who wanted a document like an existing one should COPY it, and
     * a "new" that guessed at content would be a worse copy.
     *
     * @param string  $type  Definition::REPORT or Definition::FORM
     * @throws DocumentStoreException
     */
    public function create(string $type, string $title, string $group, ?string $datasetId,
                           bool $serial = false): int
    {
        $title = $this->cleanTitle($title);
        $this->refuseDuplicateTitle($title, 0);

        if ($datasetId !== null && !$this->catalog->has($datasetId)) {
            throw new DocumentStoreException("No dataset is registered as '$datasetId'.");
        }
        // Without one, nothing can serve the document and it lists as
        // unrunnable from the moment it is made. Refused here rather than
        // discovered later, on a page that cannot say why.
        if ($datasetId === null) {
            throw new DocumentStoreException('A new document needs a dataset to draw its values from.');
        }

        $dataset    = $this->catalog->get($datasetId);
        $isForm     = $type === Definition::FORM;
        $definition = new Definition(
            type:        $type,
            title:       $title,
            group:       $group,
            datasetId:   $datasetId,
            template:    $isForm && !$serial ? new Template() : null,
            serial:      $isForm && $serial,
            serialTemplate: $isForm && $serial ? new SerialTemplate() : null,
            recordTable: $isForm ? $dataset->baseTable : '',
        );

        return $this->write($definition, $group, $isForm ? 'frm' : 'rpt');
    }

    /**
     * A copy, under a new title.
     *
     * The valuable one, and nearly free: starting from the invoice that is
     * nearly right is the normal case and starting blank is the rare one.
     *
     * The copy is written in the NATIVE format whatever the original was in,
     * because it is written by Forms — the same thing that happens the first
     * time either editor saves. The original is untouched.
     *
     * @throws DocumentStoreException
     */
    public function copy(int $id, string $title): int
    {
        $title = $this->cleanTitle($title);
        $this->refuseDuplicateTitle($title, 0);

        $row = $this->row($id);
        try {
            $original = $this->loader->load($id);
        } catch (DefinitionException $e) {
            throw new DocumentStoreException($e->getMessage(), 0, $e);
        }

        return $this->write($this->retitled($original, $title),
            (string)$row['doc_group'], (string)$row['doc_ext']);
    }

    /**
     * Rename, in both places it is written.
     *
     * The catalogue row carries the title every list shows and the definition
     * carries the one every printed document shows. They are the same name, and
     * a rename that moved only one of them is how a report comes to be called
     * two different things depending on where you look at it.
     *
     * @throws DocumentStoreException
     */
    public function rename(int $id, string $title): void
    {
        $title = $this->cleanTitle($title);
        $this->row($id);
        $this->refuseDuplicateTitle($title, $id);

        try {
            $this->loader->save($this->retitled($this->loader->load($id), $title), $id);
        } catch (DefinitionException $e) {
            throw new DocumentStoreException($e->getMessage(), 0, $e);
        }
        $this->db->Execute("update " . $this->table . " set doc_title = '"
            . $this->db->prepare_input($title) . "', last_update = '" . date('Y-m-d')
            . "' where id = " . (int)$id);
    }

    /**
     * Refuse a name already taken, before an editor writes anything.
     *
     * The editors save the definition themselves and then update the row, so
     * they need the guard BEFORE either — a rename that wrote the file and then
     * found the name taken would leave the two disagreeing, which is the whole
     * thing this class exists to prevent.
     *
     * @throws DocumentStoreException
     */
    public function assertTitleFree(string $title, int $exceptId): void
    {
        $this->refuseDuplicateTitle($this->cleanTitle($title), $exceptId);
    }

    /**
     * The catalogue row's title, for an editor that has already saved the file.
     *
     * @throws DocumentStoreException
     */
    public function updateTitle(int $id, string $title): void
    {
        $title = $this->cleanTitle($title);
        $this->refuseDuplicateTitle($title, $id);
        $this->db->Execute("update " . $this->table . " set doc_title = '"
            . $this->db->prepare_input($title) . "', last_update = '" . date('Y-m-d')
            . "' where id = " . (int)$id);
    }

    /**
     * Remove a document, its definition and the original kept beside it.
     *
     * The `.pre-forms` copy goes too. It exists to make the FIRST save
     * reversible, not to outlive the document it belongs to — leaving it would
     * accumulate orphans that the next document to be given that id would
     * inherit as its own history.
     *
     * @throws DocumentStoreException
     */
    public function delete(int $id): void
    {
        $row = $this->row($id);

        // A folder is not a document, and deleting one here would orphan
        // everything filed under it rather than delete it.
        if ((string)$row['doc_type'] === '0') {
            throw new DocumentStoreException('That is a folder, not a document.');
        }

        $setting = $this->settingNaming($id);
        if ($setting !== null) {
            throw new DocumentStoreException(
                "The setting $setting points at this document. Change that setting first,"
                . ' or deleting it would leave the setting naming nothing.');
        }

        // The row first: a file with no row is invisible but findable by its
        // name, where a row with no file is a document that lists and then
        // cannot be opened.
        $this->db->Execute("delete from " . $this->table . " where id = " . (int)$id);
        $path = $this->loader->path($id);
        if (is_file($path))                 @unlink($path);
        if (is_file($path . '.pre-forms'))  @unlink($path . '.pre-forms');
    }

    /**
     * The groups this company files documents under, as group => folder title.
     *
     * Read from the folder rows rather than from a fixed list, because the set
     * is a company's own: an extension module adds its folder when it installs.
     *
     * @return array<string,string>
     */
    public function groups(bool $forForm): array
    {
        $groups = [];
        $result = $this->db->Execute("select doc_group, doc_title from " . $this->table . "
            where doc_type = '0' and doc_ext = '" . ($forForm ? 'ff' : '0') . "'
            order by doc_title");
        while ($result && !$result->EOF) {
            $groups[(string)$result->fields['doc_group']] = (string)$result->fields['doc_title'];
            $result->MoveNext();
        }
        return $groups;
    }

    /**
     * Whether this is the only document its Print button could reach.
     *
     * Not a refusal — a group may be one nothing points at, and an admin is
     * entitled to remove the last document in it. It is what the confirmation
     * should SAY, because the button that stops working is on another page
     * entirely and nothing would connect the two afterwards.
     */
    public function isLastInGroup(int $id): bool
    {
        $row = $this->row($id);
        $result = $this->db->Execute("select count(*) as n from " . $this->table . "
            where doc_group = '" . $this->db->prepare_input((string)$row['doc_group']) . "'
              and doc_ext = '" . $this->db->prepare_input((string)$row['doc_ext']) . "'
              and doc_type <> '0'");
        return $result && !$result->EOF && (int)$result->fields['n'] <= 1;
    }

    /** The configuration key pointing at this document, if one does. */
    public function settingNaming(int $id): ?string
    {
        if (!defined('TABLE_CONFIGURATION')) return null;
        if ($id <= 0) return null;
        $result = $this->db->Execute("select configuration_key from " . TABLE_CONFIGURATION . "
            where configuration_value = '" . (int)$id . "'
              and configuration_key regexp '" . self::SETTING_NAMES . "'");
        return $result && !$result->EOF ? (string)$result->fields['configuration_key'] : null;
    }

    // ── the shared half ──────────────────────────────────────────────────────

    /**
     * Insert the row, then write the definition under the id it was given.
     *
     * The id has to exist before the file can be named, because the filename IS
     * the id — so a failed write leaves a row with no file, and that row is
     * removed rather than left listing a document that cannot be opened.
     */
    private function write(Definition $definition, string $group, string $extension): int
    {
        $this->db->Execute("insert into " . $this->table . "
            (parent_id, doc_type, doc_title, doc_group, doc_ext, security, create_date, last_update)
            values (" . (int)$this->folderFor($group, $extension) . ", 'c', '"
            . $this->db->prepare_input($definition->title) . "', '"
            . $this->db->prepare_input($group) . "', '"
            . $this->db->prepare_input($extension) . "', 'u:0;g:0', '"
            . date('Y-m-d') . "', '" . date('Y-m-d') . "')");
        $id = (int)$this->db->insert_ID();
        if ($id <= 0) throw new DocumentStoreException('The document could not be added to the catalogue.');

        try {
            $this->loader->save($definition, $id);
        } catch (DefinitionException $e) {
            $this->db->Execute("delete from " . $this->table . " where id = " . $id);
            throw new DocumentStoreException($e->getMessage(), 0, $e);
        }
        return $id;
    }

    /** The folder a group is filed under, or the miscellaneous one. */
    private function folderFor(string $group, string $extension): int
    {
        $isForm = $extension === 'frm';
        $result = $this->db->Execute("select id from " . $this->table . "
            where doc_group = '" . $this->db->prepare_input($group) . "'
              and doc_type = '0' and doc_ext in ('ff', 'fr', '0')");
        if ($result && !$result->EOF) return (int)$result->fields['id'];

        $result = $this->db->Execute("select id from " . $this->table . "
            where doc_group = '" . ($isForm ? 'misc:misc' : 'misc') . "' and doc_type = '0'");
        return $result && !$result->EOF ? (int)$result->fields['id'] : 0;
    }

    /** @return array<string,mixed> @throws DocumentStoreException */
    private function row(int $id): array
    {
        $result = $this->db->Execute("select * from " . $this->table . " where id = " . (int)$id);
        if (!$result || $result->EOF) {
            throw new DocumentStoreException("No document is catalogued as $id.");
        }
        return $result->fields;
    }

    /** @throws DocumentStoreException */
    private function cleanTitle(string $title): string
    {
        // The column is 64 characters, and a title cut off by the database is a
        // document named something its author did not choose.
        $title = trim(preg_replace('/\s+/', ' ', $title));
        if ($title === '')          throw new DocumentStoreException('A document needs a name.');
        if (strlen($title) > 64)    throw new DocumentStoreException('That name is longer than 64 characters.');
        return $title;
    }

    /**
     * Two documents of the same name is not merely untidy.
     *
     * Several places resolve a document BY TITLE — the invoice a payment link
     * attaches is chosen with `order by doc_title limit 1` (§13.26). A duplicate
     * title makes which one they find a matter of collation.
     *
     * @throws DocumentStoreException
     */
    private function refuseDuplicateTitle(string $title, int $exceptId): void
    {
        $result = $this->db->Execute("select id from " . $this->table . "
            where doc_title = '" . $this->db->prepare_input($title) . "'
              and doc_type <> '0' and id <> " . (int)$exceptId);
        if ($result && !$result->EOF) {
            throw new DocumentStoreException("A document called '$title' already exists.");
        }
    }

    /** The same definition under another name. */
    private function retitled(Definition $d, string $title): Definition
    {
        return new Definition(
            id: '', type: $d->type, title: $title, description: $d->description, group: $d->group,
            filenamePrefix: $d->filenamePrefix, emailMessage: $d->emailMessage,
            provider: $d->provider, datasetId: $d->datasetId, page: $d->page,
            dateDefault: $d->dateDefault, dateField: $d->dateField, period: $d->period,
            filters: $d->filters, sorts: $d->sorts, columns: $d->columns,
            groupBy: $d->groupBy, groupFormat: $d->groupFormat, totalsOnly: $d->totalsOnly,
            resolvedColumns: $d->resolvedColumns, style: $d->style, template: $d->template,
            breakField: $d->breakField, recordTable: $d->recordTable, recordKey: $d->recordKey,
            printedField: $d->printedField,
            serial: $d->serial, serialTemplate: $d->serialTemplate,
        );
    }
}
