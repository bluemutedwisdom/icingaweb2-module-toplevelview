<?php
/* Copyright (C) 2017 Icinga Development Team <info@icinga.com> */

namespace Icinga\Module\Toplevelview\Monitoring;

use Icinga\Data\ConnectionInterface;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Module\Monitoring\DataView\Hostgroupsummary as IcingaHostgroupsummary;

/**
 * Patched version of Hostgroupsummary
 *
 * Just to load a patched version of HostgroupsummaryQuery
 */
class Hostgroupsummary extends IcingaHostgroupsummary
{
    /** @noinspection PhpMissingParentConstructorInspection */
    /**
     * @param ConnectionInterface $connection
     * @param array|null          $columns
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(
        ConnectionInterface $connection,
        array $columns = null,
        $notification_periods = false,
        $host_never_unhandled = false
    ) {
        /** @var MonitoringBackend $connection */
        $this->connection = $connection;
        $this->query = new HostgroupsummaryQuery(
            $connection->getResource(),
            $columns,
            $notification_periods,
            $host_never_unhandled
        );
    }
}
