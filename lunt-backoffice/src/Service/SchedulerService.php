<?php

namespace App\Service;

use App\Message\{ExtexingConfigMessage, IntexingConfigMessage};
use App\Repository\IndexingConfigRepository;
use Symfony\Component\Lock\Key;
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

        foreach ($tasks as $task) {
            $key = new Key(sprintf("index-%d#%d",$task->getIndexCore()?->getId(),$task->isIndexType()));
            $lock = $this->factory->createLockFromKey($key,500,false); // 5 seconds
            if (!$lock->acquire()) {dump("lock failed on $key");}
            $schedule->add(RecurringMessage::every($task->getFrequency(), $task->isIndexType() ?
                new ExtexingConfigMessage(serialize($key),$task->getId()) : new IntexingConfigMessage(serialize($key),$task->getId())));
        }
        //$schedule->lock($this->factory->createLock(self::SCHEDULER_LOCK));

        return $schedule;
    }
}