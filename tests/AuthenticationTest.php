<?php
/**
 * Authentication Unit Tests
 * 
 * Tests for the unified login system in Smart School
 */

use PHPUnit\Framework\TestCase;

class AuthenticationTest extends TestCase
{
    /**
     * Test that the unified login page loads successfully
     */
    public function testUnifiedLoginPageExists()
    {
        $view_file = APPPATH . 'views/auth.php';
        $this->assertFileExists($view_file, 'Unified login view should exist');
    }

    /**
     * Test that Site controller has auth method
     */
    public function testSiteControllerHasAuthMethod()
    {
        $controller_file = APPPATH . 'controllers/Site.php';
        $content = file_get_contents($controller_file);
        
        $this->assertStringContainsString('public function auth()', $content, 
            'Site controller should have auth() method');
    }

    /**
     * Test that Site controller has helper methods for login processing
     */
    public function testSiteControllerHasLoginHelperMethods()
    {
        $controller_file = APPPATH . 'controllers/Site.php';
        $content = file_get_contents($controller_file);
        
        $this->assertStringContainsString('_process_staff_login', $content, 
            'Site controller should have _process_staff_login() method');
        $this->assertStringContainsString('_process_user_login', $content, 
            'Site controller should have _process_user_login() method');
    }

    /**
     * Test that Auth library redirects to unified login
     */
    public function testAuthLibraryRedirectsToUnifiedLogin()
    {
        $auth_file = APPPATH . 'libraries/Auth.php';
        $content = file_get_contents($auth_file);
        
        // Check that old login URLs are replaced with site/auth
        $this->assertStringNotContainsString("redirect('site/login')", $content, 
            'Auth library should not redirect to site/login');
        $this->assertStringNotContainsString("redirect('site/userlogin')", $content, 
            'Auth library should not redirect to site/userlogin');
        
        // Check that unified auth is used
        $this->assertStringContainsString("redirect('site/auth')", $content, 
            'Auth library should redirect to site/auth');
    }

    /**
     * Test that logout redirects to unified login
     */
    public function testLogoutRedirectsToUnifiedLogin()
    {
        $controller_file = APPPATH . 'controllers/Site.php';
        $content = file_get_contents($controller_file);
        
        // Find logout function and check if it redirects to site/auth
        preg_match('/function logout\(\)[^{]*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}/', $content, $matches);
        
        if (isset($matches[1])) {
            $logout_content = $matches[1];
            $this->assertStringContainsString("redirect('site/auth')", $logout_content, 
                'Logout should redirect to site/auth');
        } else {
            $this->fail('Could not find logout function');
        }
    }

    /**
     * Test unified login view form posts to correct URL
     */
    public function testUnifiedLoginFormAction()
    {
        $view_file = APPPATH . 'views/auth.php';
        $content = file_get_contents($view_file);
        
        $this->assertStringContainsString("site_url('site/auth')", $content, 
            'Unified login form should post to site/auth');
    }

    /**
     * Test staff model checkLogin method exists
     */
    public function testStaffModelHasCheckLoginMethod()
    {
        $model_file = APPPATH . 'models/Staff_model.php';
        $content = file_get_contents($model_file);
        
        $this->assertStringContainsString('function checkLogin', $content, 
            'Staff model should have checkLogin method');
    }

    /**
     * Test user model checkLogin method exists
     */
    public function testUserModelHasCheckLoginMethod()
    {
        $model_file = APPPATH . 'models/User_model.php';
        $content = file_get_contents($model_file);
        
        $this->assertStringContainsString('function checkLogin', $content, 
            'User model should have checkLogin method');
    }

    /**
     * Test that auth method checks staff before users
     */
    public function testAuthMethodChecksStaffFirst()
    {
        $controller_file = APPPATH . 'controllers/Site.php';
        $content = file_get_contents($controller_file);
        
        // Find position of staff check and user check in auth method
        $staff_check_pos = strpos($content, 'staff_model->checkLogin');
        $user_check_pos = strpos($content, 'user_model->checkLogin');
        
        $this->assertNotFalse($staff_check_pos, 'Should check staff login');
        $this->assertNotFalse($user_check_pos, 'Should check user login');
        
        // Staff check should come before user check
        $this->assertLessThan($user_check_pos, $staff_check_pos, 
            'Staff login should be checked before user login');
    }
}
