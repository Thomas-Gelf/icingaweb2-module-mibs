<?php

namespace Icinga\Module\Mibs\Web\Form;

use Exception;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Mibs\Db;
use Icinga\Module\Mibs\Object\MibFile;
use Icinga\Module\Mibs\Object\MibUpload;
use ipl\Html\Html;
use RuntimeException;

use function array_key_exists;
use function file_get_contents;
use function is_uploaded_file;
use function sha1;
use function unlink;

class MibForm extends Form
{
    use TranslationHelper;

    /** @var Db */
    private $db;
    private $failed = false;
    private $fileCount = 0;

    public function __construct(Db $db)
    {
        $this->db = $db;
        $this->on(Form::ON_REQUEST, [$this, 'onRequest']);
    }

    protected function assemble()
    {
        $this->getAttributes()->add('enctype', 'multipart/form-data');
        $this->add(Html::tag('div', ['class' => 'mib-drop-zone']));
        $this->addElement('text', 'uploaded_file[]', [
            'type'        => 'file',
            'label'       => $this->translate('Choose file'),
            'ignore'      => true,
            'multiple'    => 'multiple',
        ]);
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Upload')
        ]);
    }

    protected function processUploadedSource(): bool
    {
        if (! array_key_exists('uploaded_file', $_FILES)) {
            throw new RuntimeException('Got no file');
        }

        foreach ($_FILES['uploaded_file']['tmp_name'] as $key => $tmpFile) {
            if (! is_uploaded_file($tmpFile)) {
                continue;
            }
            $originalFilename = $_FILES['uploaded_file']['name'][$key];

            $source = file_get_contents($tmpFile);
            unlink($tmpFile);

            $this->fileCount++;
            $checkSum = sha1($source, true);
            if (MibFile::exists($checkSum, $this->db)) {
                $mibFile = MibFile::load($checkSum, $this->db);
            } else {
                $mibFile = MibFile::fromFileString($source);
                $mibFile->store($this->db);
            }
            MibUpload::forMibFile($mibFile, $originalFilename)->store($this->db);
        }

        return true;
    }

    protected function onRequest()
    {
        if ($this->hasBeenSent()) {
            try {
                $this->processUploadedSource();
            } catch (Exception $e) {
                $this->add(Hint::error($e->getMessage()));
            }
        }
    }

    public function countUploadedFiles(): int
    {
        return $this->fileCount;
    }
}
