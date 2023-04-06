<?php

namespace Icinga\Module\Mibs\Web\Tree;

use gipfl\IcingaWeb2\Link;
use Icinga\Module\Mibs\MibTree;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class MibTreeRenderer extends BaseHtmlElement
{
    protected $tag = 'ul';

    protected $defaultAttributes = [
        'class'            => 'tree',
        'data-base-target' => '_next',
    ];

    protected $tree;

    /** @var string */
    protected $mibName;

    public function __construct(MibTree $tree)
    {
        $this->tree = $tree->getRoot();
        $this->mibName = $tree->getName();
    }

    protected function assemble()
    {
        $this->add($this->dumpTree($this->tree));
    }

    protected function getRelativeOid($oid)
    {
        return substr($oid, strrpos($oid, '.') + 1);
    }

    protected function oidSort($left, $right)
    {
        $left = $this->getRelativeOid($left['oid']);
        $right = $this->getRelativeOid($right['oid']);
        $result = $left < $right ? -1 : 1;

        return $result;
    }

    protected function dumpTree($tree, $level = 0)
    {
        $hasChildren = ! empty($tree['children']);
//        $type = $this->tree->getType();
        $type = 'service';

        $li = Html::tag('li');
        if (! $hasChildren) {
            $li->getAttributes()->add('class', 'collapsed');
        }

        if ($hasChildren) {
            $li->add(Html::tag('span', ['class' => 'handle']));
        }

        $title = sprintf('%s (%s)', $tree['path'], $tree['oid']);
        if ($level === 0) {
            $li->add(Html::tag('a', [
                'name'  => $tree['name'],
                'class' => 'icon-globe',
                'title' => $title,
            ], $tree['name']));
        } else {
            $li->add(Link::create($tree['name'], 'mibs/mib/object', [
                'name' => $tree['name'],
                'oid'  => $tree['oid'],
                'mibName' => $this->mibName,
            ], [
                'class' => 'icon-' . $type,
                'title' => $title,
            ]));
        }

        if ($hasChildren) {
            $li->add(
                $ul = Html::tag('ul')
            );
            \uasort($tree['children'], [$this, 'oidSort']);
            foreach ($tree['children'] as $child) {
                $ul->add($this->dumpTree($child, $level + 1));
            }
        }

        return $li;
    }
}
