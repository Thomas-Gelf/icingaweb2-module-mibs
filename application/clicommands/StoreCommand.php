<?php

namespace Icinga\Module\Mibs\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Mibs\Db;
use Icinga\Module\Mibs\Object\MibFile;
use Icinga\Module\Mibs\Object\MibUpload;
use Icinga\Module\Mibs\Object\Mib;
use Icinga\Module\Mibs\Object\Node;
use Icinga\Module\Mibs\Processing\MibProcessor;

class StoreCommand extends Command
{
    /** @var \Zend_Db_Profiler */
    protected $profiler;

    public function processAction()
    {
        $connection = $this->db();
        $db = $connection->getDbAdapter();
        $processor = new MibProcessor($connection);
        if ($mib = $this->params->get('mib')) {
            $query = $db->select()
                ->from(['smf' => MibFile::TABLE], 'smf.*')
                ->join(['sm' => Mib::TABLE], 'sm.mib_checksum = smf.mib_checksum', [])
                ->where('sm.mib_name = ?', $mib);
        } elseif ($mib = $this->params->get('file')) {
            $query = $db->select()
                ->from(['smf' => MibFile::TABLE], 'smf.*')
                ->join(['smu' => MibUpload::TABLE], 'smu.mib_file_checksum = smf.mib_file_checksum', [])
                ->where('smu.original_filename = ?', $mib);
        } else {
            $query = $db->select()
                ->from(['smf' => MibFile::TABLE], 'smf.*')
                // ->where('last_processing_error IS NULL')
                // ->where('mib_checksum IS NULL')
                // ->where('last_processing_error IS NOT NULL')
                // ->where("last_processing_error not like ?", '%killed%')
                // ->order('RAND()')
                // ->where("last_processing_error like ?", '%Unable to launch a new process%')
                // ->where("last_processing_error like ?", '%killed%')
                // ->where("last_processing_error like ?", '%Got no result from parser%')
                ->joinLeft(['sm' => Mib::TABLE], 'sm.mib_checksum = smf.mib_checksum', [])
                // ->joinLeft(['smn' => Node::TABLE], 'smn.mib_checksum = smf.mib_checksum AND smn.oid IS NULL', [])
                ->group('smf.mib_file_checksum')
                ->where('sm.mib_checksum IS NOT NULL');
                // ->where('sm.mib_checksum IS NULL OR smn.mib_checksum IS NOT NULL')
            $query = $db->select()
                // ->from(['smf' => MibFile::TABLE], 'sm.mib_name')
                ->from(['smf' => MibFile::TABLE], 'smf.*')
                ->join(['sm'  => Mib::TABLE], 'sm.mib_checksum = smf.mib_checksum', [])
                ->join(['smn' => Node::TABLE], 'sm.mib_checksum = smn.mib_checksum AND smn.oid IS NULL', [])
                ->group('smf.mib_file_checksum');
        }

        foreach ($db->fetchAll($query) as $row) {
            $file = MibFile::create((array) $row);
            $file->setBeingLoadedFromDb();
            $processor->process($file);
        }
        /*
        printf(
            "Processed %d queries in %.02fs\n",
            $this->profiler->getTotalNumQueries(),
            $this->profiler->getTotalElapsedSecs()
        );
        */
    }

    protected function db(): Db
    {
        $db = Db::fromResourceName($this->Config()->get('db', 'resource'));
        // $this->profiler = new \Zend_Db_Profiler(true);
        // $db->getDbAdapter()->setProfiler($this->profiler);
        assert($db instanceof Db);
        return $db;
    }
}
