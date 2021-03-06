<?php
/* Copyright (C) 2017 Icinga Development Team <info@icinga.com> */

namespace Icinga\Module\Toplevelview\Tree;

use Icinga\Application\Benchmark;
use Icinga\Exception\NotFoundError;

class TLVHostNode extends TLVIcingaNode
{
    protected $type = 'host';

    protected $key = 'host';

    protected static $titleKey = 'host';

    public static function fetch(TLVTree $root)
    {
        Benchmark::measure('Begin fetching hosts');

        if (! array_key_exists('host', $root->registeredObjects) or empty($root->registeredObjects['host'])) {
            throw new NotFoundError('No hosts registered to fetch!');
        }

        $names = array_keys($root->registeredObjects['host']);

        $hosts = $root->getBackend()->select()
            ->from('hoststatus', array(
                'host_name',
                'host_hard_state',
                'host_handled',
                'host_in_downtime',
                'host_notifications_enabled',
            ))
            ->where('host_name', $names);

        foreach ($hosts as $host) {
            $root->registeredObjects['host'][$host->host_name] = $host;
        }

        Benchmark::measure('Finished fetching hosts');
    }

    public function getStatus()
    {
        if ($this->status === null) {
            $this->status = $status = new TLVStatus();
            $key = $this->getKey();

            if (($data = $this->root->getFetched($this->type, $key)) !== null) {
                $status->zero();
                $status->add('total');

                $state = $data->host_hard_state;

                if ($data->host_in_downtime > 0 || $data->host_notifications_enabled === '0') {
                    $status->add('downtime_active');
                    $state = '10';
                    $handled = '';
                } elseif ($data->host_handled === '1' || $this->getRoot()->get('host_never_unhandled') === true) {
                    $handled = '_handled';
                } else {
                    $handled = '_unhandled';
                }

                if ($state === '0') {
                    $status->add('ok');
                } elseif ($state === '1' || $state === '2') {
                    $status->add('critical' . $handled);
                } elseif ($state === '10') {
                    $status->add('downtime_handled');
                } else {
                    $status->add('unknown');
                }
            } else {
                $status->add('missing', 1);
            }
        }
        return $this->status;
    }
}
