<?php

namespace Icinga\Module\Mibs\Controllers;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Widget\NameValueTable;
use gipfl\Json\JsonString;
use gipfl\Web\Widget\Hint;
use Icinga\Application\Hook;
use Icinga\Module\Mibs\ActionController;
use Icinga\Module\Mibs\Formatting;
use Icinga\Module\Mibs\Forms\MibForm;
use Icinga\Module\Mibs\Object\MibFile;
use Icinga\Module\Mibs\MibTree;
use Icinga\Module\Mibs\Object\MibNode;
use Icinga\Module\Mibs\Object\MibUpload;
use Icinga\Module\Mibs\Object\Mib;
use Icinga\Module\Mibs\Processing\ParsedMibProcessor;
use Icinga\Module\Mibs\Web\Form\WalkForm;
use Icinga\Module\Mibs\Web\Table\MibsTable;
use Icinga\Module\Mibs\Web\Table\NodeDetailsTable;
use Icinga\Module\Mibs\Web\Table\NodesTable;
use Icinga\Module\Mibs\Web\Table\ObjectDetailsTable;
use Icinga\Module\Mibs\Web\Tree\MibTreeRenderer;
use Icinga\Web\Notification;
use ipl\Html\Html;
use ipl\Html\HtmlString;
use ipl\Html\Table;

class MibController extends ActionController
{
    /**
     * @return string[]
     */
    protected function getMibFileChecksumsForMib(Mib $mib): array
    {
        $db = $this->db()->getDbAdapter();
        $fileUuids = [];
        $query = $db->select()
            ->from(MibFile::TABLE, 'mib_file_checksum')
            ->where('mib_checksum = ?', $mib->get('mib_checksum'));
        return $db->fetchCol($query);
    }

    public function indexAction()
    {
        $mib = Mib::load(hex2bin($this->params->getRequired('checksum')), $this->db());
        $mibChecksums = $this->getMibFileChecksumsForMib($mib);
        if (empty($mibChecksums)) {
            $this->addSingleTab($this->translate('MIB'));
        } else {
            $file = MibFile::load($this->getMibFileChecksumsForMib($mib)[0], $this->db());
            $this->mibTabs($file, $mib)->activate('mib'); // TODO: This makes it slower, as tabs acces parsed mib
        }
        $table = new NodesTable($mib);
        $total = (int) $this->db()->getDbAdapter()->fetchOne($table->select('COUNT(*)'));
        $this->addTitle(MibsTable::label($mib) . ': ' . sprintf($this->translate('contains %d OIDs'), $total));
        $this->showTable($table, $this->translate('There are no processed nodes in this MIB file'));
    }

    public function nodeAction()
    {
        $this->addSingleTab($this->translate('MIB Node'));
        $nodeName = $this->params->get('node');
        $mib = Mib::load(hex2bin($this->params->getRequired('mib')), $this->db());
        $this->addTitle($mib->get('mib_name') . '::' . $nodeName);
        $node = MibNode::load([
            'mib_checksum' => $mib->get('mib_checksum'),
            'object_name'  => $nodeName,
        ], $this->db());
        $this->content()->add(new NodeDetailsTable($node));

        if ($implementation = Hook::first('mibs/SnmpScanTarget')) {
            $walkForm = new WalkForm(
                $node->get('oid'),
                $this->Window()->getSessionNamespace('mibs'),
                $implementation
            );
            $walkForm->handleRequest($this->getServerRequest());
            $this->content()->add($walkForm);
        }
    }

    public function uploadAction()
    {
        $this->addSingleTab($this->translate('MIB Upload'));
        $this->addTitle($this->translate('Add SNMP MIB file(s)'));
        $form = (new MibForm($this->db()))->handleRequest($this->getServerRequest());
        $count = $form->countUploadedFiles();
        if ($count > 0) {
            if ($count === 1) {
                Notification::success($this->translate('MIB file has been enqueued'));
                $this->redirectNow($this->url());
            } else {
                Notification::success(sprintf(
                    $this->translate('%d MIB files have been enqueued'),
                    $count
                ));
            }
            $this->redirectNow($this->url());
        }
        $this->content()->add($form);
    }

    public function treeAction()
    {
        $mibFile = $this->requireMibFile();
        $this->mibTabs($mibFile)->activate('tree');
        $parsed = $mibFile->getParsedMib();
        $this->addMibTitle($this->translate('MIB tree'), $parsed->name);
        try {
            $tree = new MibTreeRenderer(new MibTree($parsed));
            $this->content()->add($tree)->addAttributes(['class' => 'icinga-module module-director']);
        } catch (\Exception $e) {
            $this->content()->add(Hint::error($e->getMessage()));
        }
    }

    public function parsedAction()
    {
        $mibFile = $this->requireMibFile();
        $this->mibTabs($mibFile)->activate('parsed');
        $parsed = $mibFile->getParsedMib();
        $this->addMibTitle($this->translate('Parsed File'), $parsed->name);
        $this->content()->add(Html::tag('pre', print_r($parsed, 1)));
    }

    public function rawAction()
    {
        $mibFile = $this->requireMibFile();
        $this->mibTabs($mibFile)->activate('raw');
        $this->addTitle($this->translate('Uploaded raw MIB File'));
        if ($mibFile->hasProcessingErrors()) {
            $this->content()->add(
                Hint::error(preg_replace('/Parser exited with 1: STDOUT: /', '', $mibFile->getLastProcessingError()))
            );
        }
        $string = '';
        $cnt = 0;
        $lines = preg_split('/\r?\n/', $mibFile->get('content'));
        $length = strlen((string) count($lines));
        $pre = Html::tag('pre', ['style' => 'height: 100%; overflow-y: auto']);
        foreach ($lines as $line) {
            $cnt++;
            $pre->add([
                // Html::tag('span', ['class' => 'ignore-on-select'], sprintf('%' . $length . "d ", $cnt)),
                new HtmlString('<span class="ignore-on-select">' . sprintf('%' . $length . "d ", $cnt) . '</span>'),
                "$line\n"
            ]);
            // .ignore-on-select
            $string .= sprintf('%' . $length . "d %s\n", $cnt, $line);
        }
        $this->content()->add($pre);
    }

    protected function objectsTable($mibName, $objects, $url)
    {
        $table = new Table();
        $table->setAttributes([
            'class' => 'common-table table-row-selectable',
            'data-base-target' => '_next',
        ]);
        foreach ((array) $objects as $name => $trap) {
            $table->add($table::row([
                Link::create($mibName . '::' . $name, $url, [
                    'name'    => $name,
                    'mibName' => $mibName,
                ])
            ]));
        }

        return $table;
    }

    public function trapsAction()
    {
        $mibFile = $this->requireMibFile();
        $this->mibTabs($mibFile)->activate('traps');
        $parsed = $mibFile->getParsedMib();
        $this->addMibTitle($this->translate('Traps'), $parsed->name);
        $this->content()->add($this->objectsTable($parsed->name, $parsed->traps, 'mibs/mib/trap'));
        // $this->content()->add(Html::tag('pre', print_r($parsed->traps, 1)));
        // ->{trapName}->type = 'NOTIFICATION-TYPE', ->status = 'current', '->description, ->oid[ oidName, numId ]
        // ->{trapName}->objects =
    }

    public function typesAction()
    {
        $mibFile = $this->requireMibFile();
        $this->mibTabs($mibFile)->activate('types');
        $parsed = $mibFile->getParsedMib();
        $this->addMibTitle($this->translate('Types'), $parsed->name);
        $this->content()->add($this->objectsTable($parsed->name, $parsed->types, 'mibs/mib/type'));
        // ->{typeName}->status = 'current', ->description,
        //             ->syntax->type = INTEGER, BITS, ... BITS hat auch values
        //             ->syntax->values = [1 => val, 3, val]
        //            _->syntax->range = { min = 0, max = 100 }
        //            _->syntax->size->range = { min = 0, max = 100 }
        // ->{typeName}->reference = "...defined in RFC ..."
        // ->{typeName}->display-hint = "1x:" (hat auch type)
        // bsp: "2d-1d-1d,1d:1d:1d.1d,1a1d:1d" -> SNMPv2-TC -> DateAndTime
        // ->{typeName}->size]->choice = { 0 => 8, 1 => 11 }
        // Wenn ->{typeName}->type = SEQUENCE, dann ->items = { itemName =  { type = TypeName }
        //                  wobei Type immer entweder ein definierter oder ein Primitive ist
        // interessant: SNMPv2-TC ->RowStatus
        // ->{typeName}->implicit = true  (SNMPv2-SMI)
        // ->{typeName}->tag = [0 => APPLICATION, 1 => 2]  (SNMPv2-SMI)
        // Wenn ->{typeName}->type = CHOICE, dann ->items = { itemName =  { type = TypeName }
    }

    public function macrosAction()
    {
        $mibFile = $this->requireMibFile();
        $this->mibTabs($mibFile)->activate('macros');
        $parsed = $mibFile->getParsedMib();
        $this->addMibTitle($this->translate('Macros'), $parsed->name);
        $this->content()->add([
            $this->translate('This MIB defines the following Macros:'),
            Html::tag('br'),
            Html::tag('ul', Html::wrapEach($parsed->macros, 'li')),
        ]);
        //  SNMPv2-TC -> macros = [ 0 => 'TEXTUAL-CONVENTION' ]
        //  SNMPv2-CONF -> macros = [ 0 => OBJECT-GROUP, 1 => NOTIFICATION-GROUP,
        //                          2 => MODULE-COMPLIANCE, 3 => AGENT-CAPABILITIES ]
        // SNMPv2-SMI: macros-> MODULE-IDENTITY, OBJECT-IDENTITY, OBJECT-TYPE, NOTIFICATION-TYPE
    }

    public function processAction()
    {
        $mibFile = $this->requireMibFile();
        $this->mibTabs($mibFile)->activate('process');
        $this->addTitle($this->translate('Process uploaded MIB file'));
        $parsed = $mibFile->getParsedMib();
        if ($parsed === null) {
            $upload = MibUpload::loadMostRecentForFile($mibFile);
            $this->addTitle($upload->get('original_filename'));
            if ($mibFile->hasProcessingErrors()) {
                $hint = Hint::error($mibFile->getLastProcessingError());
            } else {
                $hint = Hint::warning($this->translate('This MIB has not yet been processed'));
            }
            $this->content()->add($hint);
            return;
        }

        [$shortName, $identity] = ParsedMibProcessor::getIdentity($parsed);

        $revisions = null;
        if ($identity === null) {
            $this->content()->add(Hint::warning('This MIB has no MODULE-IDENTITY'));
        } else {
            $table = new NameValueTable();
            $table->addNameValuePairs([
                $this->translate('Description') => $this->pre(Formatting::stringCleanup($identity->description)),
                $this->translate('Organization') => Formatting::stringCleanup($identity->organization),
                $this->translate('Organization') => Formatting::stringCleanup($identity->organization),
                $this->translate('Contact Info') => $this->pre(Formatting::stringCleanup($identity->{'contact-info'})),
                $this->translate('Last Update') => Formatting::stringCleanup($identity->{'last-updated'}),
            ]);
            $this->content()->add($table);
            if (isset($identity->revision)) {
                $revisions = new NameValueTable();
                foreach ($identity->revision as $rev) {
                    $revisions->addNameValueRow(
                        Formatting::stringCleanup($rev->revision),
                        $this->pre(Formatting::stringCleanup($rev->description))
                    );
                }
            }
        }

        $dependencies = new NameValueTable();
        if (empty($parsed->imports)) {
            $dependencies->addNameValueRow('-', $this->translate('This MIB has no IMPORTS / dependencies'));
        } else {
            $dependencies->addNameValueRow(
                Html::tag('strong', null, $this->translate('MIB')),
                Html::tag('strong', null, $this->translate('Imported Objects'))
            );
            $imports = (array) $parsed->imports;
            ksort($imports);
            $db = $this->db();
            foreach ($imports as $import => $objects) {
                if ($checksum = MibUpload::getNewestMibChecksumForName($import, $db)) {
                    $import = Link::create($import, 'mibs/mib', [
                        'checksum' => bin2hex($checksum)
                    ]);
                }

                $dependencies->addNameValueRow($import, implode(', ', $objects));
            }
        }
        $this->content()->add([
            Html::tag('h3', $this->translate('Imports')),
            $dependencies,
            Html::tag('h3', $this->translate('Revisions')),
            $revisions,
        ]);
    }

    public function objectAction()
    {
        $oid = $this->requireOid();
        $mibName = $this->params->getRequired('mibName');
        $name = $this->params->getRequired('name');
        $this->addSingleTab($this->translate('Object Details'));
        $this->addTitle("$mibName::$name ($oid)");
        if (! $this->assertValidOidOrShowError($oid)) {
            return;
        }

        $mib = $this->requireParsedMibByName($mibName);

        if (!isset($mib->nodes->$name)) {
            $this->content()->add(Hint::error(sprintf(
                $this->translate("There is no %s node in %s"),
                $name,
                $mibName
            )));
            return;
        }

        $node = $mib->nodes->$name;
        $this->content()->add(new ObjectDetailsTable($mibName, $name, $node, $oid));
    }

    public function trapAction()
    {
        $mibName = $this->params->getRequired('mibName');
        $name = $this->params->getRequired('name');
        $this->addSingleTab($this->translate('Trap Details'));
        $mib = $this->requireParsedMibByName($mibName);
        if (!isset($mib->traps->$name)) {
            $this->content()->add(Hint::error(sprintf(
                $this->translate("There is no %s trap in %s"),
                $name,
                $mibName
            )));
            return;
        }
        $trap = $mib->traps->$name;
        if (isset($trap->oid)) {
            $oid = implode('.', $trap->oid); // TODO: full OID lookup
            $this->addTitle("$mibName::$name ($oid)");

            $this->content()->add(new ObjectDetailsTable($mibName, $name, $trap, $oid));
        } else {
            $this->addTitle("$mibName::$name");

            $this->content()->add(new ObjectDetailsTable($mibName, $name, $trap));
        }
    }

    public function typeAction()
    {
        $mibName = $this->params->getRequired('mibName');
        $name = $this->params->getRequired('name');
        $this->addSingleTab($this->translate('Type Details'));
        $mib = $this->requireParsedMibByName($mibName);
        if (!isset($mib->types->$name)) {
            $this->content()->add(Hint::error("There is no $name type in $mibName"));
            return;
        }
        $type = $mib->types->$name;
        $this->addTitle("$mibName::$name");

        $this->content()->add(new ObjectDetailsTable($mibName, $name, $type));
    }

    protected function requireParsedMibByName($mibName)
    {
        $db = $this->db();
        $refId = MibUpload::getNewestUuidForName($mibName, $db);
        $upload = MibUpload::load($refId, $db);
        $mibFile = $this->getUploadedFile($upload);

        return JsonString::decode($mibFile->get('parsed_mib'));
    }

    protected function requireMibFile(): MibFile
    {
        return MibFile::load(hex2bin($this->params->getRequired('checksum')), $this->db());
    }

    protected function getUploadedFile(MibUpload $upload)
    {
        return MibFile::load($upload->get('mib_file_checksum'), $this->db);
    }

    protected function requireOid()
    {
        return Formatting::cheatOidLookup($this->params->getRequired('oid'));
    }

    protected function assertValidOidOrShowError(string $oid): bool
    {
        if (!Formatting::isValidOid($oid)) {
            $this->content()->add(Hint::error("'$oid' is not a valid OID"));
            return false;
        }

        return true;
    }

    protected function addMibTitle($title, $mibName)
    {
        $this->addTitle($mibName . ': ' . $title);
    }

    protected function pre($content)
    {
        return Html::tag('pre', [
            'style' => 'margin: 0; padding: 0;',
        ], $content);
    }

    protected function mibTabs(MibFile $mibFile, ?Mib $mib = null)
    {
        // $params = ['uuid' => Uuid::fromBytes($upload->get('mib_upload_uuid'))->toString()];
        $tabs = $this->tabs();
        if ($mib === null && $mibChecksum = $mibFile->get('mib_checksum')) {
            $mib = Mib::load($mibChecksum, $this->db());
        }
        if ($mib) {
            $tabs->add('mib', [
                'label' => $this->translate('MIB'),
                'url'   => 'mibs/mib',
                'urlParams' => ['checksum' => bin2hex($mib->get('mib_checksum'))],
            ]);
        }
        $params = ['checksum' => bin2hex($mibFile->get('mib_file_checksum'))];
        $parsed = $mibFile->getParsedMib();
        $tabs->add('process', [
            'label' => $this->translate('MIB Header'),
            'url'   => 'mibs/mib/process',
            'urlParams' => $params
        ]);

        if (isset($parsed->nodes) && ! empty((array) $parsed->nodes)) {
            $tabs->add('tree', [
                'label' => $this->translate('Tree'),
                'url'   => 'mibs/mib/tree',
                'urlParams' => $params
            ]);
        }
        if (isset($parsed->traps)) {
            $tabs->add('traps', [
                'label' => $this->translate('Traps'),
                'url'   => 'mibs/mib/traps',
                'urlParams' => $params
            ]);
        }
        if (isset($parsed->types)) {
            $tabs->add('types', [
                'label' => $this->translate('Types'),
                'url'   => 'mibs/mib/types',
                'urlParams' => $params
            ]);
        }
        if (isset($parsed->macros)) {
            $tabs->add('macros', [
                'label' => $this->translate('Macros'),
                'url'   => 'mibs/mib/macros',
                'urlParams' => $params
            ]);
        }

        $tabs->add('raw', [
            'label' => $this->translate('Raw MIB'),
            'url'   => 'mibs/mib/raw',
            'urlParams' => $params
        ]);
        if ($parsed) {
            $tabs->add('parsed', [
                'label' => $this->translate('Parsed MIB'),
                'url'   => 'mibs/mib/parsed',
                'urlParams' => $params
            ]);
        }

        return $tabs;
    }
}
