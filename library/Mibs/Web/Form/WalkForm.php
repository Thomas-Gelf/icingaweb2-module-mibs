<?php

namespace Icinga\Module\Mibs\Web\Form;

use gipfl\IcingaWeb2\Widget\NameValueTable;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Mibs\Hook\SnmpScanTargetHook;
use Icinga\Web\Session\SessionNamespace;
use ipl\Html\Html;
use React\EventLoop\Factory;

use function Clue\React\Block\await;

class WalkForm extends Form
{
    const SESSION_KEY_LAST_TARGET = 'last_scan_target';
    use TranslationHelper;

    /** @var string */
    protected $oid;
    /** @var SessionNamespace */
    protected $session;
    /** @var SessionNamespace */
    protected $windowSession;
    /** @var SnmpScanTargetHook */
    protected $targetHook;

    public function __construct(string $oid, SessionNamespace $session, SessionNamespace $windowSession, SnmpScanTargetHook $targetHook)
    {
        $this->oid = $oid;
        $this->session = $session;
        $this->windowSession = $windowSession;
        $this->targetHook = $targetHook;
    }

    protected function assemble()
    {
        $target = $this->windowSession->get(self::SESSION_KEY_LAST_TARGET);
        if ($target === null) {
            $target = $this->session->get(self::SESSION_KEY_LAST_TARGET);
        }
        $this->addElement('select', 'target', [
            'label'   => $this->translate('Target'),
            'value'   => $target,
            'options' => [null => $this->translate('- please choose -')] + $this->targetHook->enumTargets(),
            'class'   => 'autosubmit',
        ]);
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Walk')
        ]);

        // Not so nice... but something is wrong with onRequest
        if ($this->hasBeenSent() && ! $this->hasBeenSubmitted()) {
            $this->onSuccess();
        }
    }

    protected function onSuccess()
    {
        $this->ensureAssembled();
        $target = $this->getValue('target');
        if (!$this->hasBeenSent() || $target === null) {
            return;
        }
        $loop = Factory::create();
        $community = $this->targetHook->getCredential($target);
        $targetIp = $this->targetHook->getDestination($target);
        $this->session->set(self::SESSION_KEY_LAST_TARGET, $target);
        $client = $this->targetHook->getRemoteSnmpClient($target, $loop);
        $start = microtime(true);

        $result = await($client->walk($this->oid, $targetIp, $community, null)->then(function ($result) use ($start) {
            $result = (array) $result;
            $durationMs = (microtime(true) - $start) * 1000;
            if (empty($result)) {
                $this->add(sprintf(
                    $this->translate('Got an empty result in %.2dms'),
                    $durationMs
                ));
            }
            $final = [];
            foreach ($result as $key => $value) {
                $final[preg_replace('/^' . preg_quote($this->oid, '/') . '\./', '', $key)]
                    = self::getReadableSnmpValue($value);
            }
            $table = new NameValueTable();
            foreach ($final as $key => $value) {
                $table->addNameValueRow([$this->oid . '.', Html::tag('strong', $key), ':'], $value);
            }
            $this->add(Hint::ok(sprintf(
                $this->translate('Got %d results in %.2fms'),
                count($final),
                $durationMs
            )));
            $this->add($table);

            return $result;
        }, function (\Exception $e) {
            $this->add(Hint::error($e->getMessage()));
        }), $loop);
    }

    protected static function getReadableSnmpValue($value)
    {
        switch ($value->type) {
            case 'octet_string':
                if (substr($value->value, 0, 2) === '0x') {
                    $bin = hex2bin(substr($value->value, 2));

                    if (ctype_print($bin) || mb_check_encoding($bin, 'UTF-8')) {
                        return $bin;
                    }

                    if (mb_check_encoding($bin, 'ISO8859-15')) {
                        return mb_convert_encoding($bin, 'ISO8859-15', 'UTF8');
                    }
                }

                return $value->value;
            case 'oid':
            case 'gauge32':
            case 'counter32':
            case 'counter64':
            case 'ip_address':
                return $value->value;
            case 'time_ticks':
                return $value->value / 100;
            default:
                return $value;
        }
    }
}
