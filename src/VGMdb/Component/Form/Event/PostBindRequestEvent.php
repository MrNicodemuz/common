<?php

/*
 * This code was originally part of CraueFormFlowBundle.
 *
 * (c) 2011-2013 Christian Raue
 */

namespace VGMdb\Component\Form\Event;

use VGMdb\Component\Form\FormFlow;

/**
 * Event called once for the current step after binding the request.
 *
 * @author Marcus Stöhr <dafish@soundtrack-board.de>
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2013 Christian Raue
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
class PostBindRequestEvent extends FormFlowEvent
{
    /**
     * @var mixed
     */
    protected $formData;

    /**
     * @var integer
     */
    protected $step;

    /**
     * @param FormFlow $flow
     * @param mixed $formData
     * @param integer $step
     */
    public function __construct(FormFlow $flow, $formData, $step)
    {
        $this->flow = $flow;
        $this->formData = $formData;
        $this->step = $step;
    }

    /**
     * @return mixed
     */
    public function getFormData()
    {
        return $this->formData;
    }

    /**
     * @return integer
     */
    public function getStep()
    {
        return $this->step;
    }
}
