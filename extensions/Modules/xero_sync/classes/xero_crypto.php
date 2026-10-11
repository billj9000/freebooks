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
//  Path: /modules/xero_sync/classes/xero_crypto.php
//
// Encrypts the Xero client secret and tokens for storage, with libsodium's secretbox and a key
// kept in a file in the company's my_files folder, xero_sync.key. The key is not tied to a login
// session, so a scheduled job can read the tokens too. Anyone holding both the key file and the
// database can read the tokens, so a backup that carries both needs the same care as the tokens.
//
// The key is made on first use. Deleting it makes the stored tokens unreadable, and the module
// then asks for Xero to be connected again.
//

class xero_crypto {

  private string $path;

  public function __construct(string $company) {
    $this->path = DIR_FS_MY_FILES . $company . '/xero_sync.key';
  }

  public function encrypt(string $plain): string {
    if ($plain === '') return '';
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key(true)));
  }

  // The plain text, or null when the value cannot be read: the key file is missing or another
  // company's, or the value was altered.
  public function decrypt(string $stored): ?string {
    if ($stored === '') return '';
    $key = $this->key(false);
    $raw = base64_decode($stored, true);
    if ($key === null || $raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
                                          substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
    return $plain === false ? null : $plain;
  }

  // The key, made when $create and there is none. A new key is written to a temporary file and
  // then linked into place, which fails if the file exists, so two requests making it at once
  // agree on one complete key: the loser reads the winner's.
  private function key(bool $create): ?string {
    if (!is_file($this->path)) {
      if (!$create) return null;
      $temp = $this->path . '.' . bin2hex(random_bytes(6));
      file_put_contents($temp, base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
      chmod($temp, 0600);
      @link($temp, $this->path);
      unlink($temp);
    }
    $key = base64_decode(trim((string)@file_get_contents($this->path)), true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
      if ($create) throw new RuntimeException('The Xero key file ' . $this->path . ' cannot be read.');
      return null;
    }
    return $key;
  }
}
