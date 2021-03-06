<?php
/* Copyright (C) 2017 Icinga Development Team <info@icinga.com> */

namespace Icinga\Module\Toplevelview\Tree;

use Icinga\Application\Benchmark;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Toplevelview\Monitoring\Servicestatus;

class TLVServiceNode extends TLVIcingaNode
{
    protected $type = 'service';

    protected $key = '{host}!{service}';

    public function getTitle()
    {
        return sprintf(
            '%s: %s',
            $this->get('host'),
            $this->get('service')
        );
    }

    public function register()
    {
        // also register host, because that's what we fetch data with
        $hostDummy = new TLVHostNode();
        $this->root->registerObject($hostDummy->getType(), $this->get('host'), get_class($hostDummy));

        // register myself
        return parent::register();
    }

    public function getKey()
    {
        return sprintf('%s!%s', $this->properties['host'], $this->properties['service']);
    }

    public static function fetch(TLVTree $root)
    {
        Benchmark::measure('Begin fetching services');

        if (! array_key_exists('service', $root->registeredObjects) or empty($root->registeredObjects['service'])) {
            throw new NotFoundError('No services registered to fetch!');
        }

        $names = array_keys($root->registeredObjects['host']);

        $columns = array(
            'host_name',
            'service_description',
            'service_hard_state',
            'service_handled',
            'service_handled_wo_host',
            'service_notifications_enabled',
            'service_notification_period',
            'service_is_flapping',
            'service_in_downtime',
        );

        if ($root->get('notification_periods') === true) {
            $columns[] = 'service_in_notification_period';
        }

        // Note: this uses a patched version of Servicestatus / ServicestatusQuery !
        $services = new Servicestatus($root->getBackend(), $columns);
        $services->where('host_name', $names);

        foreach ($services as $service) {
            $key = sprintf('%s!%s', $service->host_name, $service->service_description);
            if (array_key_exists($key, $root->registeredObjects['service'])) {
                $root->registeredObjects['service'][$key] = $service;
            }
        }

        Benchmark::measure('Finished fetching services');
    }

    public function getStatus()
    {
        if ($this->status === null) {
            $this->status = $status = new TLVStatus();
            $key = $this->getKey();

            if (($data = $this->root->getFetched($this->type, $key)) !== null) {
                $status->zero();
                $status->add('total');

                $state = $data->service_hard_state;

                if ($this->getRoot()->get('notification_periods') === true) {
                    $notInPeriod = $data->service_in_notification_period === '0';
                } else {
                    $notInPeriod = false;
                }

                if ($this->getRoot()->get('host_never_unhandled') === true) {
                    $isHandled = $data->service_handled_wo_host === '1';
                } else {
                    $isHandled = $data->service_handled === '1';
                }
                $isHandled = $isHandled || $data->service_is_flapping === '1';

                if (
                    $data->service_in_downtime > 0
                    || $data->service_notifications_enabled === '0'
                    || $notInPeriod
                ) {
                    $status->add('downtime_active');
                    if ($state !== '0') {
                        $state = '10';
                    }
                }

                if ($isHandled) {
                    $handled = '_handled';
                } else {
                    $handled = '_unhandled';
                }

                if ($state === '0' || $state === '99') {
                    $status->add('ok', 1);
                } elseif ($state === '1') {
                    $status->add('warning' . $handled, 1);
                } elseif ($state === '2') {
                    $status->add('critical' . $handled, 1);
                } elseif ($state === '10') {
                    $status->add('downtime_handled');
                } else {
                    $status->add('unknown', 1);
                }
            } else {
                $status->add('missing', 1);
            }
        }
        return $this->status;
    }
}
