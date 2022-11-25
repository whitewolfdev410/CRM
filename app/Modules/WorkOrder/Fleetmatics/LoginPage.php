<?php

namespace App\Modules\WorkOrder\Fleetmatics;

use App\Modules\ExternalServices\Common\WebDriver\BasePage;
use Facebook\WebDriver\WebDriverBy;

class LoginPage extends BasePage
{
    /**
     * @return $this
     */
    public function open()
    {
        $this->openUrl('https://reveal.us.fleetmatics.com/login.aspx');

        return $this;
    }

    /**
     * @param $username
     * @return $this
     */
    public function typeUsername($username)
    {
        $this->driver->findElement(WebDriverBy::id('username'))->clear()->sendKeys($username);

        return $this;
    }

    /**
     * @param $password
     * @return $this
     */
    public function typePassword($password)
    {
        $this->driver->findElement(WebDriverBy::id('password'))->clear()->sendKeys($password);

        return $this;
    }

    /**
     * @return $this
     */
    public function submitLogin()
    {
        $this->driver->findElement(WebDriverBy::name('Button1'))->click();

        return $this;
    }

    /**
     * @param $username
     * @param $password
     * @return LoginPage
     */
    public function login($username, $password)
    {
        $this->typeUsername($username);
        $this->typePassword($password);

        return $this->submitLogin();
    }
}
