<?php

namespace Icinga\Module\Mibs\Processing;

use Icinga\Module\Mibs\Db;
use Icinga\Module\Mibs\Object\MibFile;
use Icinga\Module\Mibs\Object\MibUpload;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class LocalFileUploader
{
    /** @var Db */
    protected $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function uploadDirectory($path)
    {
        $files = self::getRecursiveDirectoryFiles($path);
        $connection = $this->db;
        $db = $connection->getDbAdapter();
        $hasTransaction = false;
        $count = 0;
        $size = 0;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $size += strlen($content);
            $key = sha1($content, true);
            if (! $hasTransaction) {
                $hasTransaction = true;
                $db->beginTransaction();
            }
            $mibFile = MibFile::loadOptional($key, $connection);
            if (! $mibFile) {
                $mibFile = MibFile::fromFileString($content);
                $mibFile->store($connection);
            }
            $upload = MibUpload::forMibFile($mibFile, basename($file));
            $upload->store($connection);
            $count++;
            if (($count >= 100) || ($size > 1024 * 1024 * 5)) {
                $db->commit();
                $hasTransaction = false;
                $count = 0;
                $size = 0;
            }
        }
        if ($hasTransaction) {
            $db->commit();
        }
    }

    protected static function getRecursiveDirectoryFiles($path): array
    {
        $directory = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($directory);
        $files = [];
        /** @var \SplFileInfo $info */
        foreach ($iterator as $info) {
            if ($info->isFile()) {
                $files[] = $info->getPathname();
            }
        }

        return $files;
    }
}
