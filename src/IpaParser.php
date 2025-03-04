<?php

namespace Bamalik1996\IpaParserPhp;

use \ZipArchive;

class IPAParser {
    private $ipaFilePath;
    private $infoPlistPath;
    private $xmlPlistPath;
    private $extractDir;

    public function __construct($ipaFilePath) {
        $this->ipaFilePath = realpath($ipaFilePath);
        $this->extractDir = dirname($this->ipaFilePath);
    }

    public function extractInfoPlist() {
        $zip = new ZipArchive();

        if ($zip->open($this->ipaFilePath) === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (preg_match('!Payload/[^/]+\.app/Info\.plist!', $entry)) {
                    $this->infoPlistPath = $this->extractDir . '/extracted_Info.plist';
                    $zip->extractTo($this->extractDir, $entry);
                    rename($this->extractDir . '/' . $entry, $this->infoPlistPath);
                    break;
                }
            }
            $zip->close();
        }

        return $this->infoPlistPath;
    }

    public function convertPlistToXml() {
        if (!$this->infoPlistPath) {
            return null;
        }

        $this->xmlPlistPath = $this->extractDir . '/converted_Info.xml';
        exec("plutil -convert xml1 -o {$this->xmlPlistPath} {$this->infoPlistPath}");
        return $this->xmlPlistPath;
    }

    public function getAppInfo() {
        if (!$this->xmlPlistPath) {
            return null;
        }

        $xml = simplexml_load_file($this->xmlPlistPath);
        $info = [];
        $keys = $xml->dict->key;

        for ($i = 0; $i < count($keys); $i++) {
            $key = (string)$keys[$i];
            if ($key === 'CFBundleURLTypes') {
                continue;
            }
            $valueNode = $keys[$i]->xpath('following-sibling::*[1]')[0];

            if ($valueNode->getName() == 'array') {
                $value = [];
                foreach ($valueNode->children() as $child) {
                    $value[] = (string)$child;
                }
            } else {
                $value = (string)$valueNode;
            }

            $info[$key] = $value;
        }

        return $info;
    }
}

/*$parser = new IPAParser('path_to_your_ipa_file.ipa');

if ($parser->extractInfoPlist()) {
    $parser->convertPlistToXml();
    $appInfo = $parser->getAppInfo();

    echo "App Name: " . $appInfo['CFBundleName'] . "\n";
    echo "Bundle Identifier: " . $appInfo['CFBundleIdentifier'] . "\n";
    echo "Version: " . $appInfo['CFBundleShortVersionString'] . "\n";
    // ... and so on for other keys you're interested in
} else {
    echo "Failed to extract Info.plist.";
}
*/