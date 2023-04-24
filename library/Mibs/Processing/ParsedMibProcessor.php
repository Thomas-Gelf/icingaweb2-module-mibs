<?php

namespace Icinga\Module\Mibs\Processing;

use Icinga\Module\Mibs\Db;
use Icinga\Module\Mibs\Formatting;
use Icinga\Module\Mibs\MibTreeNew;
use Icinga\Module\Mibs\Object\MibFile;
use Icinga\Module\Mibs\Object\MibImport;
use Icinga\Module\Mibs\MibTree;
use Icinga\Module\Mibs\Object\Mib;
use Icinga\Module\Mibs\Object\Node;
use Zend_Db_Select as ZfSelect;

class ParsedMibProcessor
{
    /** @var Db */
    protected $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public static function getIdentity($parsed): array
    {
        $identity = null;
        $shortName = null;
        foreach ($parsed->nodes as $name => $node) {
            if (isset($node->type) && $node->type === 'MODULE-IDENTITY') {
                if ($identity !== null) {
                    throw new \RuntimeException(sprintf(
                        'Got MODULE-IDENTITY twice, "%s" and "%s"',
                        $identity->name,
                        $node->name
                    ));
                }
                $shortName = $name;
                $identity = $node;
            }
        }

        return [$shortName, $identity];
    }

    protected function refreshMibInfo(MibFile $mibFile, MibTree $tree)
    {
        $db = $this->db->getDbAdapter();
        [$shortName, $identity] = $this->getIdentity($mibFile->getParsedMib());
        if ($shortName === null) {
            $shortName = $tree->getName(); // TODO: NO!
        }
        $checksum = $mibFile->get('mib_checksum');
        if (Mib::exists($checksum, $this->db)) {
            // echo 'EXISTING MIB: ' . $tree->getName() . "\n";
            return;
        }
        if ($identity || $shortName) {
            echo 'CREATING MIB: ' . $tree->getName() . "\n";
            $db->delete(Mib::TABLE, $db->quoteInto('mib_checksum = ?', $checksum));
            $db->insert(Mib::TABLE, [
                'mib_checksum' => $checksum,
                'mib_name' => $tree->getName(),
                'smi_version' => $identity ? 2 : 1,
                'short_name' => $shortName, // (IDENTITY) snmpTargetMIB
                'organization' => $this->cleanupOrganization(
                    Formatting::stringCleanup($identity->organization ?? null)
                ),
                'description'  => Formatting::stringCleanup($identity->description ?? null),
                'contact_info' => Formatting::stringCleanup($identity->{'contact-info'} ?? null),
                'last_updated' => Formatting::stringCleanup($identity->{'last-updated'} ?? ''),
                'ts_last_updated' => 0, // TODO: parse last_updated
            ]);
            printf("CREATED MIB: %s (%s)\n", $tree->getName(), bin2hex($checksum));
        } else {
            echo 'SKIPPING MIB: ' . $tree->getName() . "\n";
        }
    }

    protected function cleanupOrganization($string)
    {
        if ($string === null) {
            return null;
        }

        $string = trim($string);
        $string = preg_replace('/\r?\n/', ' ', $string);
        $string = preg_replace('/ +/', ' ', $string);
        $string = preg_replace('/\t/', ' ', $string);
        return $string;
    }

    protected function resolveImports($importMibName, $importedMibObjects): ZfSelect
    {
        $db = $this->db->getDbAdapter();
        return $db->select()->from(['smn' => Node::TABLE], [
            'k'   => 'smn.object_name',
            'oid' => 'smn.oid'
        ])
        ->join(
            ['sm' => Mib::TABLE],
            $db->quoteInto('smn.mib_checksum = sm.mib_checksum AND sm.mib_name = ?', $importMibName),
            []
        )
        ->join(
            ['smf' => MibFile::TABLE],
            'smf.mib_checksum = smn.mib_checksum',
            []
        )
        ->where('smn.oid IS NOT NULL')
        ->where('object_name IN (?)', $importedMibObjects);
    }

    protected function refreshImports(MibFile $mibFile): array
    {
        // TODO: zeroDotZero ??
        $loadedImports = [
            '0'   => '.0',
            'iso' => '.1',
        ];
        $parsed = $mibFile->getParsedMib();
        if (!isset($parsed->imports)) {
            return $loadedImports;
        }
        $imports = $parsed->imports;
        $db = $this->db->getDbAdapter();
        $db->beginTransaction();
        try {
            $db->delete(MibImport::TABLE, $db->quoteInto('mib_checksum = ?', $mibFile->get('mib_checksum')));
            $newImports = [];
            foreach ($imports as $importMibName => $importedMibObjects) {
                // unique -> WLSX-RS-MIB imports ArubaEnableValue twice
                $importedMibObjects = array_unique($importedMibObjects);
                foreach ($db->fetchPairs($this->resolveImports($importMibName, $importedMibObjects)) as $k => $v) {
                    $loadedImports[$k] = $v;
                }
                foreach ($importedMibObjects as $objectName) {
                    if (! isset($loadedImports[$objectName])) {
                        // printf(
                        //     "%s is missing %s::%s\n",
                        //     $parsed->name ?? 'NO MIB NAME', $importMibName, $objectName
                        // );
                    }
                    if (isset($newImports[$objectName])) {
                        printf("Skipping duplicate import: %s -> %s\n", $parsed->name ?? 'NO MIB NAME', $objectName);
                    }
                    $newImports[$objectName] = $importMibName; // This removes duplicates
                }
            }
            foreach ($newImports as $objectName => $importMibName) {
                $db->insert(MibImport::TABLE, [
                    'mib_checksum'    => $mibFile->get('mib_checksum'),
                    'source_mib_name' => $importMibName,
                    'object_name'     => $objectName,
                ]);
            }
            $db->commit();
        } catch (\Exception $e) {
            try {
                $db->rollBack();
            } catch (\Exception $e) {
            }
            throw $e;
        }

        return $loadedImports;
    }

    public function process(MibFile $mibFile)
    {
        $db = $this->db->getDbAdapter();
        $parsed = $mibFile->getParsedMib();
        $tree = new MibTree($parsed, true);
        $this->refreshMibInfo($mibFile, $tree);
        $loadedImports = $this->refreshImports($mibFile);
        try {
            $newTree = new MibTreeNew($parsed, $loadedImports);
        } catch (\Throwable $e) {
            echo $parsed->name . ': ' . $e->getMessage() . "\n";
            $newTree = null;
        }
        if (isset($parsed->nodes)) {
            if (in_array($parsed->name, ['DASAN-ROUTER-MIB', 'LINKSYS-MIB'])) {
                echo "Skipping " . $parsed->name . ", it will segfault\n";
                return;
            }
            // echo "Parsing MIB nodes: " . $parsed->name . "\n";
            $nodes = $parsed->nodes;
            $nodesWithoutOid = (int) $db->fetchOne(
                $db->select()->from(Node::TABLE, 'COUNT(*)')
                    ->where('mib_checksum = ?', $mibFile->get('mib_checksum'))
                    ->where('oid IS NULL')
            );
            $nodesWithOid = (int) $db->fetchOne(
                $db->select()->from(Node::TABLE, 'COUNT(*)')
                    ->where('mib_checksum = ?', $mibFile->get('mib_checksum'))
                    ->where('oid IS NOT NULL')
            );
            if ($nodesWithoutOid > 0 || $nodesWithOid === 0) {
                $this->runAsTransaction($db, function () use ($db, $mibFile, $nodes, $newTree) {
                    $db->delete(Node::TABLE, $db->quoteInto('mib_checksum = ?', $mibFile->get('mib_checksum')));
                    foreach ($nodes as $nodeName => $node) {
                        if ($newTree) {
                            $node->oidString = implode('.', $newTree->getNode($nodeName)->getOidPath());
                        }
                        Node::fromParsedNode($mibFile, $nodeName, $node)->insert($this->db);
                    }
                });
            } else {
                // echo "Skipping " . $parsed->name . ", seems complete\n";
            }
        } else {
            echo "Mib has no nodes: " . $parsed->name . "\n";
        }
    }

    protected function runAsTransaction($db, callable $operation)
    {
        $db->beginTransaction();
        try {
            $operation();
            $db->commit();
        } catch (\Exception $e) {
            try {
                $db->rollBack();
            } catch (\Exception $e) {
            }
            throw $e;
        }
    }
}
