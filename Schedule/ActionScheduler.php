<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 * @date 28.07.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Schedule;

use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowContext;
use Gtt\Bundle\WorkflowExtensionsBundle\Command\ExecuteActionCommand;
use Gtt\Bundle\WorkflowExtensionsBundle\Entity\Repository\ScheduledJobRepository;
use Gtt\Bundle\WorkflowExtensionsBundle\Entity\ScheduledJob;
use Gtt\Bundle\WorkflowExtensionsBundle\Schedule\ValueObject\ScheduledAction;
use Gtt\Bundle\WorkflowExtensionsBundle\WorkflowSubject\SubjectManipulator;
use Doctrine\Common\Persistence\ObjectManager;
use JMS\JobQueueBundle\Entity\Job;
use Carbon\Carbon;
use Psr\Log\LoggerInterface;

/**
 * Schedules action to be executed after some time
 */
class ActionScheduler
{
    /**
     * Persistance object manager
     *
     * @var ObjectManager
     */
    private $em;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ActionScheduler constructor.
     *
     * @param ObjectManager   $em     entity manager
     * @param LoggerInterface $logger logger
     */
    public function __construct(ObjectManager $em, LoggerInterface $logger)
    {
        $this->em     = $em;
        $this->logger = $logger;
    }

    /**
     * Schedules actions
     *
     * @param WorkflowContext $workflowContext workflow context
     * @param ScheduledAction $scheduledAction scheduled action
     */
    public function scheduleAction(WorkflowContext $workflowContext, ScheduledAction $scheduledAction)
    {
        /** @var ScheduledJobRepository $scheduledJobRepository */
        $scheduledJobRepository = $this->em->getRepository(ScheduledJob::class);

        $jobToSchedule = new Job(ExecuteActionCommand::COMMAND_NAME,
            [
                '--action='       . $scheduledAction->getName(),
                '--arguments='    . json_encode($scheduledAction->getArguments()),
                '--workflow='     . $workflowContext->getWorkflow()->getName(),
                '--subjectClass=' . get_class($workflowContext->getSubject()),
                '--subjectId='    . $workflowContext->getSubjectId(),
            ]
        );

        $scheduledJob = null;
        if ($scheduledAction->isReschedulable()) {
            $scheduledJob = $scheduledJobRepository->findScheduledJobToReschedule($jobToSchedule);
        }

        if ($scheduledJob) {
            // the job was already scheduled but not executed. Now we need to reschedule it
            $this->rescheduleActionJob($scheduledAction, $scheduledJob, $workflowContext);
        } else {
            // creating new jms job to trigger action
            $this->scheduleActionJob($scheduledAction, $jobToSchedule, $workflowContext);
        }
    }

    /**
     * Reschedules already scheduled action
     *
     * @param ScheduledAction $scheduledAction scheduled action
     * @param Job             $jobToSchedule   job to schedule
     * @param WorkflowContext $workflowContext workflow context
     */
    private function scheduleActionJob(ScheduledAction $scheduledAction, Job $jobToSchedule, WorkflowContext $workflowContext)
    {
        $executionDate = $this->getActionExecutionDate($scheduledAction);
        $jobToSchedule->setExecuteAfter($executionDate);

        $scheduledJob = new ScheduledJob($jobToSchedule, $scheduledAction->isReschedulable());

        $this->em->persist($jobToSchedule);
        $this->em->persist($scheduledJob);

        $this->em->flush();

        $this->logger->info(
            sprintf(
                "Workflow successfully scheduled action '%s' with parameters '%s'",
                $scheduledAction->getName(),
                json_encode($scheduledAction->getArguments())
            ),
            $workflowContext->getLoggerContext()
        );
    }

    /**
     * Reschedules already scheduled action
     *
     * @param ScheduledAction $scheduledAction scheduled action
     * @param ScheduledJob    $scheduledJob    scheduled job for action
     * @param WorkflowContext $workflowContext workflow context
     */
    private function rescheduleActionJob(ScheduledAction $scheduledAction, ScheduledJob $scheduledJob, WorkflowContext $workflowContext)
    {
        $actionJob = $scheduledJob->getJob();
        $actionJob->setExecuteAfter($this->getActionExecutionDate($scheduledAction));

        // since jms Job states DEFERRED_EXPLICIT change tracking policy we should explicitly persist entity now
        $this->em->persist($actionJob);
        $this->em->flush();

        $this->logger->info(
            sprintf("Workflow successfully rescheduled action '%s' with parameters '%s'",
                $scheduledAction->getName(),
                json_encode($scheduledAction->getArguments())
            ),
            $workflowContext->getLoggerContext()
        );
    }

    /**
     * Calculates execution date date for scheduled action
     *
     * @param ScheduledAction $scheduledAction scheduled action
     *
     * @return \DateTime
     */
    private function getActionExecutionDate(ScheduledAction $scheduledAction)
    {
        $executionDate = Carbon::now();
        $executionDate->add($scheduledAction->getOffset());

        return $executionDate;
    }
}