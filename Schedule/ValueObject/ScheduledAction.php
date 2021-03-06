<?php
/**
 * This file is part of the Global Trading Technologies Ltd workflow-extension-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 20.09.16
 */

namespace Gtt\Bundle\WorkflowExtensionsBundle\Schedule\ValueObject;

use Gtt\Bundle\WorkflowExtensionsBundle\Action\ValueObject\Action;
use DateInterval;

/**
 * Data Value Object represents scheduled action
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class ScheduledAction extends Action
{
    /**
     * Offset for transition
     *
     * @var DateInterval
     */
    private $offset;

    /**
     * Flag defines current scheduled action can be rescheduled or not
     *
     * @var boolean
     */
    private $isReschedulable;

    public function __construct($name, array $arguments, $offset, $isReschedulable = false)
    {
        parent::__construct($name, $arguments);
        $this->offset          = new DateInterval($offset);
        $this->isReschedulable = $isReschedulable;
    }

    /**
     * Returns offset date interval
     *
     * @return DateInterval
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @return boolean
     */
    public function isReschedulable()
    {
        return $this->isReschedulable;
    }
}