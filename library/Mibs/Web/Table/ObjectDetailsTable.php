<?php

namespace Icinga\Module\Mibs\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Table\NameValueTable;
use Icinga\Module\Mibs\Formatting;
use ipl\Html\Html;
use stdClass;

class ObjectDetailsTable extends NameValueTable
{
    use TranslationHelper;

    /** @var stdClass */
    protected $node;

    /** @var string */
    protected $mibName;

    /** @var string */
    protected $name;

    /** @var ?string */
    protected $oid;

    public function __construct(string $mibName, string $name, stdClass $node, ?string $oid = null)
    {
        $this->node = $node;
        $this->mibName = $mibName;
        $this->name = $name;
        $this->oid = $oid;
    }

    protected function assemble()
    {
        // TODO: MODULE-COMPLIANCE, ->module (CISCO-ENTITY-EXT-MIB)
        // TODO: OBJECT-GROUP -> objects (CISCO-ENTITY-EXT-MIB)
        $properties = [
            'description'  => $this->translate('Description'),
            'units'        => $this->translate('Units'), // string cleanup
            'type'         => $this->translate('Type'), // macro (OBJECT-TYPE, ...)
            'access'       => $this->translate('Access'), // not-accessible, ...
            'status'       => $this->translate('Status'), // current, ...
            'defval'       => $this->translate('Default Value'), // ??
            'reference'    => $this->translate('Reference'),
            'display-hint' => $this->translate('Display Hint'),
            'syntax'       => $this->translate('Syntax'), // json (->type
            'index'        => $this->translate('Index (Table)'), // json -> array (implied, value)
            'items'        => $this->translate('Items'), // steht im "type", nicht im node?
            'objects'      => $this->translate('Objects'), // array
            'organization' => $this->translate('Organisation'),
            'contact-info' => $this->translate('Contact Information'),
            'last-updated' => $this->translate('Last Updated'),
            'revision'     => $this->translate('Revisions'),
        ];
        $modifiers = [
            'organization' => [Formatting::class, 'stringCleanup'],
            'contact-info' => [$this, 'formatDescription'],
            'description'  => [$this, 'formatDescription'],
            'units'        => [Formatting::class, 'stringCleanup'],
            'reference'    => [Formatting::class, 'stringCleanup'],
            'index'        => [$this, 'formatIndexes'],
            'items'        => [$this, 'formatItems'],
            'objects'      => [$this, 'formatObjects'],
            'syntax'       => [$this, 'formatSyntax'],
            'revision'     => [$this, 'formatRevisions'],
            'last-updated' => [$this, 'formatDate'],
        ];

        $node = $this->node;
        $this->addNameValuePairs([
            $this->translate('MIB')  => $this->mibName,
            $this->translate('Name') => $this->name,
        ]);
        if ($this->oid) {
            $this->addNameValueRow($this->translate('OID'), Html::sprintf(
                '%s (%s.%s)',
                $this->oid,
                $this->linkToParentNode($node->oid[0]),
                $node->oid[1]
            ));
        }
        $seen = [
            'oid' => true,
        ];
        foreach ($properties as $property => $label) {
            if (isset($node->$property)) {
                $seen[$property] = true;
                $value = $node->$property;
                if (isset($modifiers[$property])) {
                    $method = $modifiers[$property];
                    $value = $method($value);
                }
                $this->addNameValueRow($label, $value);
            }
        }

        $others = [];
        foreach ((array) $node as $property => $value) {
            if (! isset($seen[$property])) {
                $others[$property] = $value;
            }
        }
        if (! empty($others)) {
            $this->addNameValueRow($this->translate('Others'), Html::tag('pre', print_r($others, 1)));
        }
    }

    protected function formatDate($date)
    {
        // TODO: Date formats
        return Formatting::stringCleanup($date);
    }

    protected function formatDescription($description)
    {
        return Html::tag('pre', Formatting::stringCleanup($description));
    }

    protected function formatRevisions($revisions)
    {
        $result = [];
        foreach ($revisions as $rev) {
            $result[] = Html::tag('strong', Formatting::stringCleanup($rev->revision));
            $result[] = Html::tag('br');
            $result[] = Html::tag('pre', Formatting::stringCleanup($rev->description));
            $result[] = Html::tag('br');
            $result[] = Html::tag('br');
        }

        return $result;
    }

    protected function formatIndexes($indexes)
    {
        $result = [];
        foreach ($indexes as $index) {
            // TODO: link!
            $result[] = $index->value . ($index->implied ? ' (implied)' : '');
            $result[] = Html::tag('br');
        }

        return $result;
    }

    protected function addNewline(&$result)
    {
        if (! empty($result)) {
            $result[] = Html::tag('br');
        }
    }

    protected function formatSyntaxValues($values)
    {
        $values = (array) $values;
        ksort($values);
        $result[] = $this->translate('possible values:');
        $result[] = $ul = Html::tag('ul');
        foreach ($values as $index => $value) {
            $ul->add(Html::tag('li', "$value ($index)"));
        }

        return $result;
    }

    protected function formatRange($range)
    {
        $rangeInfo = [];
        if (isset($range->min)) {
            $rangeInfo[] = 'min=' . $range->min;
        }
        if (isset($range->max)) {
            $rangeInfo[] = 'max=' . $range->max;
        }

        return implode(', ', $rangeInfo);
    }

    protected function formatSize($size)
    {
        if (is_object($size)) {
            if (isset($size->choice)) {
                return 'Size (choice): ' . implode(', ', $size->choice);
            } elseif (isset($size->range)) {
                return 'Size (range): ' . $this->formatRange($size->range);
            } else {
                return 'Size: ' . print_r($size, 1);
            }
        } else {
            return 'Size: ' . $size;
        }
    }

    protected function formatType($type)
    {
        if (is_string($type)) {
            return $type; // TODO: linkType?
        }

        return Html::tag('strong', 'Unsupported type formatting: ' . print_r($type, 1));
    }

    protected function linkType($type)
    {
        // TODO: Only if IN this MIB!! Otherwise -> Imports?
        return Link::create($type, 'mibs/mib/type', [
            'mibName' => $this->mibName,
            'name' => $type,
        ]);
    }

    protected function formatItems($items)
    {
        $ul = Html::tag('ul');
        foreach ((array) $items as $key => $value) {
            if (isset($value->type)) {
                // TODO: -> linkToObject($key)
                $ul->add(Html::tag('li', ["$key: ", $this->formatType($value->type)]));
            }
            if (array_keys((array) $value) !== ['type']) {
                $ul->add(Html::tag('li', ['Unsupported Item', print_r($value, 1)]));
            }
        }

        return $ul;
    }

    protected function formatObjects($objects)
    {
        $ul = Html::tag('ul');
        foreach ((array) $objects as $name) {
            // TODO: Link to object
            $ul->add(Html::tag('li', $name));
        }

        return $ul;
    }

    protected function formatSyntax($syntax)
    {
        $result = [];
        if (isset($syntax->type)) { // TODO: Type lookup or link
            $result[] = $this->formatType($syntax->type);
        }
        if (isset($syntax->values)) {
            $this->addNewline($result);
            $result[] = $this->formatSyntaxValues($syntax->values);
        }
        if (isset($syntax->size)) {
            $this->addNewline($result);
            $result[] = $this->formatSize($syntax->size);
        }
        if (isset($syntax->items)) {
            $this->addNewline($result);
            $result[] = $this->formatItems($syntax->items);
        }
        if (isset($syntax->range)) {
            $this->addNewline($result);
            $result[] = 'Range: ' . $this->formatRange($syntax->range);
        }
        //             ->syntax->type = INTEGER, BITS, ... BITS hat auch values
        //             ->syntax->values = [1 => val, 3, val]
        //            _->syntax->range = { min = 0, max = 100 }
        //            _->syntax->size->range = { min = 0, max = 100 }
        $known = [
            'type'   => true,
            'items'  => true,
            'values' => true,
            'range'  => true,
            'size'   => true,
        ];

        foreach ((array) $syntax as $key => $value) {
            if (! isset($known[$key])) {
                $this->addNewline($result);
                $result[] = [
                    Html::sprintf($this->translate('Unknown syntax property %s = '), Html::tag('strong', $key)),
                    Html::tag('pre', print_r($value, 1)),
                ];
            }
        }

        return $result;
    }

    protected function linkToParentNode($name)
    {
        if ($name === 'mib-2') {
            // TODO: Check for root and imports
            return $name;
        }

        return Link::create($name, 'mibs/mib/object', [
            'mibName' => $this->mibName,
            'name'    => $name,
            'oid'     => preg_replace('/\.\d+$/', '', $this->oid),
        ]);
    }
}
