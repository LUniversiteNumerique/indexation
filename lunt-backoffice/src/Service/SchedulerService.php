<?php

namespace App\Service;

use App\Message\{ExtexingConfigMessage, IntexingConfigMessage};
use App\Repository\IndexingConfigRepository;
use Symfony\Component\Scheduler\{Attribute\AsSchedule, RecurringMessage, Schedule, ScheduleProviderInterface};

#[AsSchedule('default')]
class SchedulerService implements ScheduleProviderInterface
{
    const SCHEDULER_LOCK = 'scheduler_default';

    public function __construct(private readonly IndexingConfigRepository $repository){}

    public function getSchedule(): Schedule
    {
        $schedule = $this->schedule ??= (new Schedule());
        $tasks = $this->repository->findAll();

        foreach ($tasks as $task)
            $schedule->add(RecurringMessage::every($task->getFrequency(), $task->isIndexType() ?
                new ExtexingConfigMessage(null,$task->getId()) : new IntexingConfigMessage(null,$task->getId())));
            //$schedule->lock($this->factory->createLock(self::SCHEDULER_LOCK));

        return $schedule;
    }
}