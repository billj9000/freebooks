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
//  Path: /modules/contacts/classes/postcode_lookup.php
//

interface PostcodeLookupProvider {
    public function getName(): string;
    /** Returns candidates: array of ['primary_name','address1','address2','city_town','state_province','postal_code'] */
    public function lookup(string $postcode, string $country): array;
}

class FetchifyProvider implements PostcodeLookupProvider {
    private string $apiKey;

    public function __construct(string $apiKey) { $this->apiKey = $apiKey; }

    public function getName(): string { return 'Fetchify'; }

    public function lookup(string $postcode, string $country): array {
        $url = 'https://api.craftyclicks.co.uk/address/1.1/find'
             . '?key='     . urlencode($this->apiKey)
             . '&query='   . urlencode($postcode)
             . '&country=' . urlencode($country);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!$raw) return [];
        $data = json_decode($raw, true);
        if (!isset($data['results'])) return [];
        $out = [];
        foreach ($data['results'] as $r) {
            $label = $r['labels'][1] ?? '';
            if ($label === '') continue;
            $parts = array_map('trim', explode(',', $label));
            $n     = count($parts);
            // 4+ parts: first part is company/building name → primary_name
            if ($n >= 4) {
                $primary = array_shift($parts);
                $n--;
            } else {
                $primary = '';
            }
            $out[] = [
                'primary_name'   => $primary,
                'address1'       => $parts[0] ?? '',
                'address2'       => $n >= 3 ? $parts[1] : '',
                'city_town'      => $parts[$n - 1] ?? '',
                'state_province' => '',
                'postal_code'    => $r['labels'][0] ?? $postcode,
            ];
        }
        return $out;
    }
}

class VehicleDataProvider implements PostcodeLookupProvider {
    private string $apiKey;

    public function __construct(string $apiKey) { $this->apiKey = $apiKey; }

    public function getName(): string { return 'Vehicle Data'; }

    public function lookup(string $postcode, string $country): array {
        $url = 'https://uk.api.vehicledataglobal.com/r2/lookup'
             . '?packageName=AddressDetails'
             . '&postcode=' . urlencode($postcode);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $this->apiKey]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!$raw) return [];
        $data = json_decode($raw, true);
        if (empty($data['ResponseInformation']['IsSuccessStatusCode'])) return [];
        $list = $data['Results']['AddressDetails']['AddressList'] ?? [];
        $out = [];
        foreach ($list as $a) {
            $f = $a['FormattedAddressLines'] ?? [];
            $out[] = [
                'primary_name'   => $f['Organisation'] ?? '',
                'address1'       => trim(($f['Premises'] ?? '') . ' ' . ($f['Street'] ?? '')),
                'address2'       => $f['Locality']                                     ?? '',
                'city_town'      => ucwords(strtolower($f['PostTown']                  ?? '')),
                'state_province' => $f['County']                                       ?? '',
                'postal_code'    => $f['Postcode']                                     ?? $postcode,
            ];
        }
        return $out;
    }
}

class PostcodeLookup {
    public static function active(): ?PostcodeLookupProvider {
        if (!defined('POSTCODE_LOOKUP_STATUS') || !POSTCODE_LOOKUP_STATUS) return null;
        $key = defined('POSTCODE_LOOKUP_API_KEY') ? POSTCODE_LOOKUP_API_KEY : '';
        if (!$key) return null;
        switch (defined('POSTCODE_LOOKUP_PROVIDER') ? POSTCODE_LOOKUP_PROVIDER : '') {
            case 'fetchify':     return new FetchifyProvider($key);
            case 'vehicle_data': return new VehicleDataProvider($key);
            default:             return null;
        }
    }
}
