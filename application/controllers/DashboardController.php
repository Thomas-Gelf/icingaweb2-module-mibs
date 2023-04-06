<?php

namespace Icinga\Module\Mibs\Controllers;

use Icinga\Module\Mibs\ActionController;
use Icinga\Util\Format;
use ipl\Html\Html;
use ipl\Html\HtmlElement;

class DashboardController extends ActionController
{
    public function indexAction()
    {
        $this->mainTabs()->activate('dashboard');
        $this->addTitle('SNMP MIB Browser');
        $this->controls()->getTitleElement()->addAttributes(['style' => 'font-size: 3em;']);
        $db = $this->db()->getDbAdapter();
        $stats = $db->fetchRow($db->select()->from('snmp_mib_stats'));
        $this->content()->add([
            $this->statLet(
                $this->translate('Nodes (OIDs)'),
                self::formatNumber($stats->cnt_nodes_total),
                $this->translate('Resolved / Unresolved'),
                self::formatNumber($stats->cnt_resolved_nodes)
                . ' / '
                . self::formatNumber($stats->cnt_unresolved_nodes)
            ),
            $this->statLet(
                $this->translate('MIB files'),
                self::formatNumber($stats->cnt_files_total),
                $this->translate('Total file size / Duplicates'),
                Format::bytes($stats->file_size_total)
                . ' / '
                . self::formatNumber($stats->cnt_duplicate_files)
            ),
            $this->statLet(
                $this->translate('Successfully parsed'),
                self::formatNumber($stats->cnt_files_parsed),
                $this->translate('Failing / Pending'),
                self::formatNumber($stats->cnt_files_failed)
                . ' / '
                . self::formatNumber($stats->cnt_files_pending)
            ),
            $this->statLet(
                $this->translate('Database Size'),
                Format::bytes($stats->db_size_total),
                $this->translate('Data / Index'),
                Format::bytes($stats->db_size_data)
                . ' / '
                . Format::bytes($stats->db_size_index)
            ),
        ]);
    }

    protected function statLet($title, $number, $subTitle, $subNumbers): HtmlElement
    {
        return Html::tag('div', ['class' => 'mib-stats',], [
            Html::tag('div', ['class' => 'stats-title'], $title),
            Html::tag('div', ['class' => 'stats-main-number'], $number),
            Html::tag('div', ['class' => 'stats-sub-title'], $subTitle),
            Html::tag('div', ['class' => 'stats-sub-numbers'], $subNumbers),
        ]);
    }

    protected static function formatNumber($number): string
    {
        return number_format($number, 0, ',', '.');
    }
}
