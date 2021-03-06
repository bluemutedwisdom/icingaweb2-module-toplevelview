<?php
/* Copyright (C) 2017 Icinga Development Team <info@icinga.com> */

namespace Icinga\Module\Toplevelview\Monitoring;

use Icinga\Module\Monitoring\Backend\Ido\Query\ServicestatusQuery as IcingaServicestatusQuery;

/**
 * Patched version of ServicestatusQuery
 */
class ServicestatusQuery extends IcingaServicestatusQuery
{
    public function init()
    {
        $patchedColumnMap = array(
            'servicestatus'             => array(
                'service_handled_wo_host' => 'CASE WHEN ss.problem_has_been_acknowledged > 0 THEN 1 ELSE 0 END',
            ),
            'servicenotificationperiod' => array(
                'service_notification_period'    => 'ntpo.name1',
                'service_in_notification_period' => 'CASE WHEN s.notification_timeperiod_object_id IS NULL THEN 1 ELSE CASE WHEN ntpr.timeperiod_id IS NOT NULL THEN 1 ELSE 0 END END',
            ),
        );

        foreach ($patchedColumnMap as $table => $columns) {
            foreach ($columns as $k => $v) {
                $this->columnMap[$table][$k] = $v;
            }
        }

        parent::init();
    }

    protected function joinServicenotificationperiod()
    {
        $this->select->joinLeft(
            array('ntp' => $this->prefix . 'timeperiods'),
            'ntp.timeperiod_object_id = s.notification_timeperiod_object_id AND ntp.config_type = 1 AND ntp.instance_id = s.instance_id',
            array()
        );
        $this->select->joinLeft(
            array('ntpo' => $this->prefix . 'objects'),
            'ntpo.object_id = s.notification_timeperiod_object_id',
            array()
        );
        $this->select->joinLeft(
            array('ntpr' => $this->prefix . 'timeperiod_timeranges'),
            'ntpr.timeperiod_id = ntp.timeperiod_id
                AND ntpr.day = DAYOFWEEK(UTC_DATE()) - 1
                AND ntpr.start_sec <= UNIX_TIMESTAMP() - UNIX_TIMESTAMP(UTC_DATE())
                AND ntpr.end_sec >= UNIX_TIMESTAMP() - UNIX_TIMESTAMP(UTC_DATE())
            ',
            array()
        );
    }
}
