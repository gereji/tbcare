<?php

namespace apps\user;

class RecoverPassword extends \apps\Application {
	
	public function doGet(){
		try {
			$page = $this->doRecoverPasswordForm();
			return $page;
		} catch (\apps\ApplicationException $e) {
			$this->onError($e);
		}
	}

	public function doPost(){
		$translator = $this->sandbox->getHelper('translation');
		$user = $this->sandbox->getHelper('user');
		try {
			$user->recoverPassword();
			$page = $this->doSignInForm();
			$page['message'][] = $translator->translate("password.changed");
			return $page;
		}catch(\apps\ApplicationException $e){
			$page = $this->doRecoverPasswordForm();
			$page['error'][] = $e->getMessage();
			return $page;
		}
	}
	
	private function doRecoverPasswordForm(){
		$translator = $this->sandbox->getHelper('translation');
		$form = $this->sandbox->getHelper('formbuilder');
		$base = $this->sandbox->getMeta('base');
		$form->setSource("$base/apps/user/forms/recoverpassword.xml");
		$form->setContent($_GET);
		$form->populate();
		$page['title'] = $translator->translate('recoverpassword');
		$page['body'] = $form->asHTML();
		return $page;
	}
	
	private function doSignInForm(){
		$translator = $this->sandbox->getHelper('translation');
		$form = $this->sandbox->getHelper('formbuilder');
		$base = $this->sandbox->getMeta('base');
		$form->setSource("$base/apps/user/forms/signin.xml");
		$form->setAction('/signin');
		$page['title'] = $translator->translate('signin');
		$page['body'] = $form->asHTML();
		return $page;
	}
	
}