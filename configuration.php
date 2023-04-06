<?php

use Icinga\Application\Modules\Module;

/** @var Module $this */
$section = $this->menuSection(N_('SNMP MIB Browser'))
    ->setUrl('mibs/dashboard')
    ->setPriority(70)
    ->setIcon('sitemap');

$section->add(N_('MIB Browser'))->setUrl('mibs/mibs');
$section->add(N_('MIB Processing'))->setUrl('mibs/processing');
