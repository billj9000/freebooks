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
//  Path: /modules/phreedom/updates/PhreeBooks371toFreeBooks100.php
//
// The phreedom module's tables, from PhreeBooks 3.7.1 to FreeBooks 1.0.0, and the
// conversion of the whole database to the character set, collation and dates
// 1.0.0 installs with.
//
// update() includes this only while the module's status is a PhreeBooks version,
// and stamps 1.0.0 only after it returns. Every statement is safe to repeat, so
// a run that stops part way is finished by the next login. A later release
// brings its own migration rather than adding to this one.
//
// The whole-database conversion is here because phreedom is upgraded last: the
// login cascade reaches every other module first, so every table and column the
// other modules add already exists when it runs.

// ---- users -----------------------------------------------------------------
// admin_pass holds a bcrypt hash, which needs 255 characters; 3.7.1 sized it for
// a sha1 digest. The four that follow carry brute-force lockout and password
// reset. One guard per column, so a database holding some of them is completed.
if (db_field_exists(TABLE_USERS, 'admin_pass')) {
    $db->Execute("ALTER TABLE " . TABLE_USERS . " MODIFY admin_pass varchar(255) NOT NULL DEFAULT ''");
}
foreach (array(
    'failed_attempts'  => "int(11) NOT NULL DEFAULT '0'",
    'locked_until'     => "datetime NULL DEFAULT NULL",
    'pw_reset_token'   => "char(64) NULL DEFAULT NULL COMMENT 'sha-256 of the emailed token, never the token'",
    'pw_reset_expires' => "datetime NULL DEFAULT NULL",
) as $column => $declaration) {
    if (!db_field_exists(TABLE_USERS, $column)) {
        $db->Execute("ALTER TABLE " . TABLE_USERS . " ADD COLUMN $column $declaration");
    }
}

// ---- Forms -----------------------------------------------------------------
// Forms replaces PhreeForm, and PhreeForm is not shipped, so an upgraded company
// is given Forms the way Module Administration installs it. Without it nothing
// defines the catalogue table, and every print path ends in a fatal. The
// catalogue and the definitions stay where PhreeForm left them: Forms reads the
// same table, and its loader converts a PhreeForm definition as it reads it.
// install() creates only what is missing and seeds nothing.
if (!defined('MODULE_FORMS_STATUS') && file_exists(DIR_FS_MODULES . 'forms/classes/install.php')) {
    gen_pull_language('forms', 'admin');
    gen_pull_language('forms');
    require_once(DIR_FS_MODULES . 'forms/config.php');
    require_once(DIR_FS_MODULES . 'forms/classes/install.php');
    $forms = new forms_admin();
    admin_install_dirs($forms->dirlist, DIR_FS_MY_FILES . $_SESSION['company'] . '/');
    admin_install_tables($forms->tables);
    foreach ($forms->keys as $key => $value) if (!defined($key)) write_configure($key, $value);
    $forms->install('forms');
    write_configure('MODULE_FORMS_STATUS', MODULE_FORMS_VERSION);
}

// ---- permissions that were PhreeForm's ---------------------------------------
// PhreeForm's security id, 3, went with that module, and an absent key reads as
// a denied one. Each role's level for 3 is copied to every key that takes over
// something it granted:
//   - SECURITY_ID_FORMS_RUN and SECURITY_ID_FORMS_AUTHOR: 3 let a role run and
//     write reports, which Forms keys separately.
//   - SECURITY_ID_DASHBOARDS: the Company Links, Notes and To-Do dashboards
//     borrowed 3 in PhreeBooks, and a dashboard's security id decides who can
//     see it.
// SECURITY_ID_FORMS_DATASETS is not copied: defining which tables may be joined
// is a privilege 3 never named on its own, and an administrator grants it.
// A key a role already holds is left as it is. 3 is written as a number because
// the constant that named it went with PhreeForm.
$inherits_3 = array(SECURITY_ID_DASHBOARDS);
if (defined('SECURITY_ID_FORMS_RUN')) {
    $inherits_3[] = SECURITY_ID_FORMS_RUN;
    $inherits_3[] = SECURITY_ID_FORMS_AUTHOR;
}
$result = $db->Execute("select admin_id, admin_security from " . TABLE_USERS
    . " where admin_security <> ''");
while ($result && !$result->EOF) {
    $id     = (int)$result->fields['admin_id'];
    $stored = (string)$result->fields['admin_security'];
    $result->MoveNext();
    $levels = gen_parse_permissions($stored);
    if (!isset($levels[3])) continue;
    $changed = false;
    foreach ($inherits_3 as $key) {
        if (isset($levels[$key])) continue;
        $levels[$key] = $levels[3];
        $changed = true;
    }
    if (!$changed) continue;
    $pairs = array();
    foreach ($levels as $key => $level) $pairs[] = $key . ':' . $level;
    $db->Execute("update " . TABLE_USERS . " set admin_security = '"
        . $db->prepare_input(implode(',', $pairs)) . "' where admin_id = " . $id);
    // The session's permissions were read before this ran, so the person whose
    // login is doing the upgrade is given the new keys now rather than at their
    // next login.
    if (isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] === $id) {
        $_SESSION['admin_security'] = $levels;
    }
}

// ---- webauthn_creds --------------------------------------------------------
// Passkeys for signing in.
if (!db_table_exists(TABLE_WEBAUTHN_CREDS)) $db->Execute($this->tables[TABLE_WEBAUTHN_CREDS]);

// ---- the whole database: character set and collation -----------------------
// A new installation is created in utf8mb4 with utf8mb4_unicode_ci and never
// runs this. An upgraded or restored one has to be converted, or it keeps a
// deprecated charset that MySQL 8 will not restore, and every later migration
// has two schemas to cope with.
//
// CONVERT TO CHARACTER SET rewrites a table, so on a large installation this
// takes time and holds a lock. It is driven from information_schema, so a second
// run finds nothing to do.

// The database's own default. A table created without an explicit CHARSET and
// COLLATE inherits it, so a database left on utf8mb3, or on MariaDB's
// utf8mb4_uca1400_ai_ci, would go on producing tables MySQL cannot read.
//
// Sent through PDO rather than $db->Execute(), because ALTER DATABASE needs a
// privilege the company's database user is not guaranteed to hold, and
// queryFactory answers a refused statement by ending the request. Every table is
// converted either way; only tables created later would inherit the old default,
// so a refusal is reported and the upgrade carries on.
$schema = $db->Execute("SELECT DATABASE() AS d, (SELECT default_collation_name
    FROM information_schema.schemata WHERE schema_name = DATABASE()) AS c");
if (!$schema->EOF && $schema->fields['c'] !== 'utf8mb4_unicode_ci') {
    try {
        $db->link->exec("ALTER DATABASE `" . str_replace('`', '', $schema->fields['d'])
                      . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        $messageStack->add(sprintf(GEN_ERROR_DATABASE_COLLATION, $schema->fields['c']), 'caution');
    }
}

// Tables not already on the target collation. The character set alone is not
// enough to test: MariaDB gives a table utf8mb4_uca1400_ai_ci when a CREATE names
// the charset and no collation, and MySQL has no such collation, so a dump of
// that table will not restore there.
$tbls = $db->Execute("SELECT table_name AS t FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
      AND table_collation <> 'utf8mb4_unicode_ci'");
while (!$tbls->EOF) {
    $db->Execute("ALTER TABLE `" . $tbls->fields['t'] . "` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $tbls->MoveNext();
}

// cred_id stays ascii: it holds base64url under a UNIQUE key at varchar(500),
// which does not fit an index at four bytes a character. The conversion above
// takes it with the rest of the table.
if (db_table_exists(TABLE_WEBAUTHN_CREDS) && db_field_exists(TABLE_WEBAUTHN_CREDS, 'cred_id')) {
    $db->Execute("ALTER TABLE " . TABLE_WEBAUTHN_CREDS
        . " MODIFY cred_id varchar(500) CHARACTER SET ascii NOT NULL DEFAULT ''"
        . " COMMENT 'base64url-encoded credential id'");
}

// ---- the whole database: dates ---------------------------------------------
// Date columns declared with a zero default cannot say "no date". Each becomes
// nullable, then its zero dates become NULL.
$cols = $db->Execute("SELECT table_name AS t, column_name AS c, column_type AS ty
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND data_type IN ('date','datetime')
      AND is_nullable = 'NO' AND column_default LIKE '%0000-00-00%'");
$zero_date_columns = array();
while (!$cols->EOF) {
    $zero_date_columns[] = array($cols->fields['t'], $cols->fields['c'], $cols->fields['ty']);
    $cols->MoveNext();
}
foreach ($zero_date_columns as $col) {
    list($t, $c, $ty) = $col;
    $db->Execute("ALTER TABLE `$t` MODIFY `$c` $ty NULL DEFAULT NULL");
    $db->Execute("UPDATE `$t` SET `$c` = NULL WHERE `$c` LIKE '0000-00-00%'");
}

// A stored zero date is what NO_ZERO_DATE refuses, whatever the column is
// declared as, so every date column is swept for values. Where the column
// accepts NULL the value becomes NULL. Where it does not, it becomes the
// column's own default, which is that column's answer to "not set"; a NOT NULL
// column whose default was itself a zero date was made nullable above.
$dates = $db->Execute("SELECT table_name AS t, column_name AS c, is_nullable AS n,
                              column_default AS d
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND data_type IN ('date','datetime')");
$date_columns = array();
while (!$dates->EOF) {
    $date_columns[] = array($dates->fields['t'], $dates->fields['c'],
                            $dates->fields['n'], $dates->fields['d']);
    $dates->MoveNext();
}
foreach ($date_columns as $col) {
    list($t, $c, $nullable, $default) = $col;
    if ($nullable === 'YES') {
        $db->Execute("UPDATE `$t` SET `$c` = NULL WHERE `$c` LIKE '0000-00-00%'");
        continue;
    }
    $default = trim((string)$default, "'");
    if ($default === '' || strpos($default, '0000-00-00') !== false) continue;
    $db->Execute("UPDATE `$t` SET `$c` = '" . db_input($default) . "' WHERE `$c` LIKE '0000-00-00%'");
}

// ---- column widths ----------------------------------------------------------
// Names, addresses and descriptions arrive from outside - the web shop, carrier
// and payment services - longer than these columns held. A lax server cut them
// to fit without a word; a strict one refuses the
// whole write, so an order with a long address line would not import at all.
// Widened as the installer now declares them; only ever widened.
db_widen_column(TABLE_AUDIT_LOG, 'reference_id', 64, "varchar(64) NOT NULL default ''");
db_widen_column(TABLE_EXTRA_FIELDS, 'description', 128, "varchar(128) NOT NULL default ''");
