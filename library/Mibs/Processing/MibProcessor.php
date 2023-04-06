<?php

namespace Icinga\Module\Mibs\Processing;

use Icinga\Module\Mibs\Db;
use Icinga\Module\Mibs\Object\MibFile;
use Icinga\Module\Mibs\MibParser;

class MibProcessor
{
    /** @var Db */
    protected $db;
    /** @var ParsedMibProcessor */
    protected $parsedProcessor;
    protected $forceParsing = false;

    public function __construct(Db $db)
    {
        $this->db = $db;
        $this->parsedProcessor = new ParsedMibProcessor($db);
    }

    public function process(MibFile $mibFile): bool
    {
        try {
            $oldParsed = $mibFile->get('parsed_mib');
            if ($oldParsed && ! $this->forceParsing) {
                $this->parsedProcessor->process($mibFile);
                return true;
            } else {
                die('wtf?');
            }
            $parsed = MibParser::parseString($mibFile->get('content'));
            if (empty($parsed)) {
                $mibFile->set('last_processing_error', 'Got no result from parser');
                $mibFile->store($this->db);
                return false;
            }
        } catch (\Exception $e) {
            $mibFile->set('last_processing_error', $e->getMessage());
            $mibFile->store($this->db);
            return false;
        }
        $mibFile->setParsedMib($parsed);
        if ($oldParsed === null) {
            echo "Parsed MIB: " . ($parsed->name ?? 'UNKNOWN') . "\n";
        } elseif ($mibFile->hasBeenModified()) {
            echo "Parsed MIB changed: " . ($parsed->name ?? 'UNKNOWN') . "\n";
        }

        $mibFile->store($this->db);
        try {
            $this->parsedProcessor->process($mibFile);
            return true;
        } catch (\Exception $e) {
            $mibFile->set('last_processing_error', $e->getMessage());
            $mibFile->store($this->db);
            return false;
        }
    }
}
