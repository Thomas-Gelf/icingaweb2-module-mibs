<?php

namespace Icinga\Module\Mibs\Object;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Mibs\Db;
use Icinga\Module\Mibs\Object\MibFile;
use Ramsey\Uuid\Uuid;

class MibUpload extends DbObject
{
    const TABLE = 'snmp_mib_upload';
    protected $table = self::TABLE;
    protected $keyName = 'uuid';

    protected $defaultProperties = [
        'uuid'              => null,
        'mib_file_checksum' => null,
        'username'          => null,
        'client_ip'         => null,
        'ts_upload'         => null,
        'original_filename' => null,
    ];

    public static function forMibFile(MibFile $file, string $filename): MibUpload
    {
        return MibUpload::create([
            'uuid'              => Uuid::uuid4()->getBytes(),
            'mib_file_checksum' => $file->get('mib_file_checksum'),
            'username'          => Auth::getInstance()->getUser()->getUsername(),
            'ts_upload'         => (int) (microtime(true) * 1000),
            'client_ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            'original_filename' => $filename,
        ]);
    }

    public static function loadMostRecentForFile(MibFile $mibFile): MibUpload
    {
        $connection = $mibFile->getConnection();
        $db = $connection->getDbAdapter();

        $self = MibUpload::create((array) $db->fetchRow(
            $db->select()
                ->from(['smu' => MibUpload::TABLE], 'uuid')
                ->where('smu.mib_file_checksum = ?', $mibFile->get('mib_file_checksum'))
                ->order('smu.ts_upload DESC')
                ->limit(1)
        ));
        $self->setBeingLoadedFromDb();

        return $self;
    }

    public static function getNewestUuidForName($name, Db $connection)
    {
        $db = $connection->getDbAdapter();

        return $db->fetchOne(
            $db->select()
                ->from(['smu' => MibUpload::TABLE], 'uuid')
                ->join(['smf' => MibFile::TABLE], 'smu.mib_file_checksum = smf.mib_file_checksum', [])
                ->join(['sm' => Mib::TABLE], 'sm.mib_checksum = smf.mib_checksum', [])
                ->where('sm.mib_name = ?', $name)
                ->order('smu.ts_upload DESC')
                ->limit(1)
        );
    }

    public static function getNewestMibChecksumForName($name, Db $connection)
    {
        $db = $connection->getDbAdapter();

        return $db->fetchOne(
            $db->select()
                ->from(['smu' => MibUpload::TABLE], 'sm.mib_checksum')
                ->join(['smf' => MibFile::TABLE], 'smu.mib_file_checksum = smf.mib_file_checksum', [])
                ->join(['sm' => Mib::TABLE], 'sm.mib_checksum = smf.mib_checksum', [])
                ->where('sm.mib_name = ?', $name)
                ->order('smu.ts_upload DESC')
                ->limit(1)
        );
    }
}
