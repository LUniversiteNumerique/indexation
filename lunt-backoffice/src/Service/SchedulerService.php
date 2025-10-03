<?php

namespace App\Service;

use App\Entity\IndexingConfig;
use App\Message\{ExtexingConfigMessage, IntexingConfigMessage};
use App\Repository\IndexingConfigRepository;
use Symfony\Component\Scheduler\{Attribute\AsSchedule, RecurringMessage, Schedule, ScheduleProviderInterface};
use Symfony\Component\Lock\LockFactory;

/**
 * Service responsable de la planification des tâches d'indexation.
 */
#[AsSchedule('default')]
readonly class SchedulerService implements ScheduleProviderInterface
{
    const SCHEDULER_LOCK = 'scheduler_default';

    /**
     * @param LockFactory $factory
     * @param IndexingConfigRepository $repository
     */
    public function __construct(
        private LockFactory $factory,
        private IndexingConfigRepository $repository
    ) {}

    /**
     * Retourne la planification des tâches d'indexation.
     *
     * @return Schedule
     */
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        $tasks = $this->repository->findAll();

        foreach ($tasks as $task) {
            $schedule->add(
                RecurringMessage::every(
                    $task->getFrequency(),
                    $this->createMessageForTask($task)
                )
            );
        }
        return $schedule;
    }

    /**
     * Crée le message approprié pour la tâche donnée.
     *
     * @param IndexingConfig $task
     * @return ExtexingConfigMessage|IntexingConfigMessage
     */
    private function createMessageForTask(IndexingConfig $task): ExtexingConfigMessage|IntexingConfigMessage
    {
        return $task->isIndexType()
            ? new ExtexingConfigMessage(null, $task->getId())
            : new IntexingConfigMessage(null, $task->getId());
    }
}