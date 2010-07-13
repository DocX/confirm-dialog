<?php

/**
 * Confirmation dialog with dynamic signals
 *
 * Copyright (c) 2009 Lukáš Doležal @ GDMT (dolezal@gdmt.cz)
 *
 * This source file is subject to the "General Public Licenee" (GPL)
 *
 * @copyright  Copyright (c) 2009 Lukáš Doležal (dolezal@gdmt.cz)
 * @license    http://www.gnu.org/copyleft/gpl.html  General Public License
 * @link       http://nettephp.com/cs/extras/confirmation-dialog
 * @package    ConfirmationDialog
 */


//UNCOMMENT IF YOU ARE USING NETTE WITH NAMESPACES 
//
//use \Nette\Application\Control;
//use \Nette\Application\AppForm;
//use \Nette\Web\Html;
//use \Nette\Environment;
//

if(!function_exists('lcfirst'))
{
    function lcfirst($string)
    {
        $string{0} = strtolower($string{0});
        return $string;
    }
}


class ConfirmationDialog extends Control
{

    // Localization strings
    public static $_strings = array(
		'yes' => 'Yes',
		'no' => 'No',
		'expired' => 'Confirmation token expires. Please try action again.',
	);

	/** @var \Nette\Application\AppForm */
	private $form;

	/** @var Nette\Web\Html Confirmation question */
	private $question;

	/** @var Nette\Web\Session */
	private $session;

	/** @var array Storage of confirmation handlers*/
	private $confirmationHandlers;

	/** @var bool */
	public $show = FALSE;

	/** @var string class of div element */
	public $dialogClass = 'confirm_dialog';

	public function __construct($parent = NULL, $name = NULL)
	{
		parent::__construct($parent, $name);

		$this->form = new AppForm($this, 'form');

		$this->form->addSubmit('yes', self::$_strings['yes'])
			->onClick[] = array($this, 'confirmClicked');
		$this->form->addSubmit('no', self::$_strings['no'])
			->onClick[] = array($this, 'cancelClicked');
		$this->form->addHidden('token');

		$this->question = Html::el('p');

		$this->session = Environment::getSession('ConfirmationDialog/tokens');
	}


	/**
	 * Overrides signal method formater. This provide "dynamicaly named signals"
	 * @param string $signal
	 * @return string
	 */
	public function formatSignalMethod($signal)
	{
		if (stripos($signal, 'confirm') === 0 &&
			isset($this->confirmationHandlers[lcfirst(substr($signal, 7))]))
			return '_handleShow';

		parent::formatSignalMethod($signal);
	}


	/**
	 * Access to Yes or No form button controls.
	 * @param string $name Only 'yes' or 'no' is accepted
	 * @return \Nette\Forms\SubmitButton
	 */
	public function getFormButton($name)
	{
		$name = (string)$name;
		if ($name !== 'yes' && $name !== 'no')
			throw new MemberAccessException("Only 'yes' or 'no' is accepted in \$name. '$name' given.");

		return $this->form[$name];
	}


	/**
	 * Return element prototype of nested Form
	 * @return \Nette\Web\Html
	 */
	public function getFormElementPrototype()
	{
		return $this->form->getElementPrototype();
	}


	/**
	 * Return question element protype
	 * @return Html
	 */
	public function getQuestionPrototype()
	{
		return $this->question;
	}


	/**
	 * Set question
	 * @param string $text
	 */
	public function setQuestionText($text)
	{
		$this->question->setText($text);
		$this->invalidateControl();
	}


	/**
	 * Generate unique token key
	 * @param string $name
	 * @return string
	 */
	protected function generateToken($name = '')
	{
		return base_convert(md5(uniqid('confirm' . $name, true)), 16, 36);
	}


	/************** configuration **************/

	/**
	 * Add confirmation handler to "dynamicaly named signals".
	 * @param string $name Confirmation/signal name
	 * @param callback $methodCallback Callback called when confirmation succeed
	 * @param callback|string $question Callback ($confirmForm, $params) or string containing question text.
	 * @return ConfirmationDialog
	 */
	public function addConfirmer($name, $methodCallback, $question)
	{
		if (!preg_match('/[A-Za-z_]+/', $name))
			throw new InvalidArgumentException("Confirmation name contain is invalid.");
	
		if (isset($this->confirmationHandlers[$name]))
			throw new InvalidArgumentException("Confirmation '$confirmName' already exists.");

		if (!is_callable($methodCallback))
			throw new InvalidArgumentException('$methodCallback must be callable.');

		if (!is_callable($question) && !is_string($question))
			throw new InvalidArgumentException('$question must be callback or string.');

		$this->confirmationHandlers[$name] = array(
			'handler' => $methodCallback,
			'question' => $question,
			);

		return $this;
	}

	/**
	 * Show dialog for confirmation
	 * @param <type> $confirmName
	 * @param <type> $params
	 */
	public function showConfirm($confirmName, $params = array())
	{
		if (!is_string($confirmName))
			throw new InvalidArgumentException('$confirmName must be string.');
		if (!isset($this->confirmationHandlers[$confirmName]))
			throw new InvalidStateException("confirmation '$confirmName' do not exist.");
		if (!is_array($params))
			throw new InvalidArgumentException('$params must be array.');

		$confirm = $this->confirmationHandlers[$confirmName];

		if (is_callable($confirm['question']))
			$question = call_user_func_array($confirm['question'], array($this, $params));
		else
			$question = $confirm['question'];

		if ($question instanceof Html)
			$this->question->setHtml($question);
		else
			$this->question->setText($question);
	
		$token = $this->generateToken($confirmName);
		$this->session->$token = array(
			'confirm' => $confirmName,
			'params' => $params,
			);

		$this->form['token']->value = $token;

		$this->show = TRUE;
		$this->invalidateControl();
	}


	/************** signals processing **************/

	/**
	 * Dynamicaly named signal receiver
	 */
	function _handleShow()
	{
		list(,$signal) = $this->presenter->getSignal();
		$confirmName = (substr($signal, 7));
		$confirmName{0} = strtolower($confirmName{0});
		$params = $this->getParam();
		
		$this->showConfirm($confirmName, $params);
	}


	/**
	 * Confirm YES clicked
	 * @param \Nette\Forms\SubmitButton $button
	 * @return void
	 */
	public function confirmClicked($button)
	{
		$form = $button->getForm(TRUE);
		$values = $form->getValues();
		if (!isset($this->session->{$values['token']}))
		{
			if (self::$_strings['expired'] != '')
			{
				$this->presenter->flashMessage(self::$_strings['expired']);
			}
			$this->invalidateControl();
			return;
		}

		$action = $this->session->{$values['token']};
		unset($this->session->{$values['token']});

		$this->show = FALSE;
		$this->invalidateControl();

		$callback = $this->confirmationHandlers[$action['confirm']]['handler'];

		$args = $action['params'];
		$args[] = $this;
		call_user_func_array($callback, $args);

		if (!$this->presenter->isAjax() && $this->show == FALSE)
			$this->presenter->redirect('this');
	}


	/**
	 * Confirm NO clicked
	 * @param \Nette\Forms\SubmitButton $button
	 */
	public function cancelClicked($button)
	{
		$form = $button->getForm(TRUE);
		$values = $form->getValues();
		if (isset($this->session->{$values['token']}))
		{
			unset($this->session->{$values['token']});
		}

		$this->show = FALSE;
		$this->invalidateControl();
		if (!$this->presenter->isAjax())
			$this->presenter->redirect('this');
	}


	/************** rendering **************/

	/**
	 *
	 * @return bool
	 */
	public function isVisible()
	{
		return $this->show;
	}

	
	/**
	 * Template factory.
	 * @return ITemplate
	 */
	protected function createTemplate()
	{
		$template = parent::createTemplate();
		// Nette filter is registered by default in Control.
		$template->setFile(dirname(__FILE__) . '/form.phtml');
		return $template;
	}

	
	public function render()
	{
		if ($this->show)
		{
			if ($this->form['token']->value == NULL)
				throw new InvalidStateException('Token is not set!');
		}
		$this->template->visible = $this->show;
		$this->template->class = $this->dialogClass;
		$this->template->question = $this->question;
		return $this->template->render();
	}

}

