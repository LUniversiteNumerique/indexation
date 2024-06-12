<?php

namespace App\Service;

use App\Message\{ExtexingConfigMessage, IntexingConfigMessage};
use App\Repository\IndexingConfigRepository;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\{Attribute\AsSchedule, RecurringMessage, Schedule, ScheduleProviderInterface};

#[AsSchedule('default')]
class SchedulerService implements ScheduleProviderInterface
{
    const SCHEDULER_LOCK = 'scheduler-default';

    public function __construct(
        private readonly LockFactory $factory,
        private readonly IndexingConfigRepository $repository
    ){}

    public function getSchedule(): Schedule
    {
        $schedule = $this->schedule ??= (new Schedule());
        $tasks = $this->repository->findAll();

        foreach ($tasks as $task)
            $schedule->add(RecurringMessage::every($task->getFrequency(), $task->isIndexType() ?
                new ExtexingConfigMessage($task->getId()) : new IntexingConfigMessage($task->getId())));
        $schedule->lock($this->factory->createLock(self::SCHEDULER_LOCK));

        return $schedule;
    }
}