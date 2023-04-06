<?php

namespace Icinga\Module\Mibs\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Mibs\Db;
use Icinga\Module\Mibs\Object\MibFile;
use Icinga\Module\Mibs\Processing\LocalFileUploader;

class FilesCommand extends Command
{
    public function exportAction()
    {
        $dir = $this->requireDirectory('target-dir');
        $db = $this->db()->getDbAdapter();
        foreach ($db->fetchAll($db->select()->from(MibFile::TABLE, ['mib_name', 'mib_checksum', 'content'])) as $row) {
            $filename = sprintf('%s_%s.mib', $row->mib_name, substr(bin2hex($row->mib_checksum), 0, 7));
            file_put_contents("$dir/$filename", $row->content);
        }
    }

    public function importAction()
    {
        $dir = $this->requireDirectory('source-dir');
        $uploader = new LocalFileUploader($this->db());
        $uploader->uploadDirectory($dir);
    }

    protected function requireDirectory($parameter): string
    {
        $dir = $this->params->shiftRequired($parameter);
        if (! is_dir($dir)) {
            $this->fail("--$parameter needs to be a directory");
        }

        return $dir;
    }

    protected function db(): Db
    {
        $db = Db::fromResourceName($this->Config()->get('db', 'resource'));
        assert($db instanceof Db);
        return $db;
    }
}
