<?php

namespace Icinga\Module\Mibs\Controllers;

use gipfl\IcingaWeb2\Link;
use Icinga\Module\Mibs\ActionController;
use Icinga\Module\Mibs\Web\Table\MibOrganizationsTable;
use Icinga\Module\Mibs\Web\Table\MibsTable;

class MibsController extends ActionController
{
    public function indexAction()
    {
        $table = new MibsTable($this->db());
        if ($organization = $this->params->get('organization')) {
            $this->addSingleTab($this->translate('SNMP MIBs'));
            $table->filterOrganization($organization);
            $this->addTitle(sprintf($this->translate('SNMP MIBs: %s'), $organization === MibOrganizationsTable::NULL ? $this->translate('(none)') : $organization));
        } else {
            $this->mainTabs()->activate('mibs');
            $this->addTitle($this->translate('SNMP MIBs'));
        }

        $this->actions()->add(
            Link::create($this->translate('Add'), 'mibs/mib', null, [
                'class' => 'icon-plus',
                'data-base-target' => '_next'
            ])
        );

        $this->showTable($table, $this->translate('There are no processed MIB files in this DB'));
    }
}
