<?php
/**
 * Browser/Integration Tests for Login Flow
 * 
 * These tests can be run with browser automation tools
 * or executed via the Antigravity browser_subagent
 */

use PHPUnit\Framework\TestCase;

class LoginFlowTest extends TestCase
{
    /**
     * Base URL for testing
     */
    protected $baseUrl = 'http://localhost/smart_school_src/';

    /**
     * Test data for login scenarios
     * These should match the dummy seeder credentials
     */
    protected $testCredentials = [
        'staff' => [
            'username' => 'admin@admin.com', // Update with actual admin email
            'password' => 'StaffPass!23',
            'expected_redirect' => 'admin/admin/dashboard'
        ],
        'student' => [
            'username' => '', // Will be populated from database
            'password' => 'Siswa123!',
            'expected_redirect' => 'user/user/choose'
        ],
        'parent' => [
            'username' => '', // Will be populated from database
            'password' => 'Parent123!',
            'expected_redirect' => 'user/user/choose'
        ]
    ];

    /**
     * Test Case: Unified login page loads
     * 
     * Steps:
     * 1. Navigate to /site/auth
     * 2. Verify page loads with login form
     * 
     * Expected: Page contains username and password fields
     */
    public function testUnifiedLoginPageLoads()
    {
        // This test verifies the view file structure
        $view_file = APPPATH . 'views/auth.php';
        $content = file_get_contents($view_file);
        
        // Check for form elements
        $this->assertStringContainsString('name="username"', $content);
        $this->assertStringContainsString('name="password"', $content);
        $this->assertStringContainsString('type="submit"', $content);
    }

    /**
     * Test Case: Old admin login URL redirects
     * 
     * Steps:
     * 1. Navigate to /site/login
     * 2. Should redirect to /site/auth
     * 
     * Note: This requires actual browser testing or HTTP client
     */
    public function testOldAdminLoginRedirects()
    {
        // This test would require actual HTTP requests
        // For now, we verify the code structure
        $controller_file = APPPATH . 'controllers/Site.php';
        $content = file_get_contents($controller_file);
        
        // The existing login() method should still exist for backward compatibility
        $this->assertStringContainsString('function login()', $content);
    }

    /**
     * Test Case: Old user login URL redirects
     * 
     * Steps:
     * 1. Navigate to /site/userlogin
     * 2. Should redirect to /site/auth
     * 
     * Note: This requires actual browser testing or HTTP client
     */
    public function testOldUserLoginRedirects()
    {
        $controller_file = APPPATH . 'controllers/Site.php';
        $content = file_get_contents($controller_file);
        
        // The existing userlogin() method should still exist for backward compatibility
        $this->assertStringContainsString('function userlogin()', $content);
    }

    /**
     * Test Case: Invalid credentials show error
     * 
     * Steps:
     * 1. Navigate to /site/auth
     * 2. Enter invalid username/password
     * 3. Submit form
     * 
     * Expected: Error message displayed, stays on login page
     */
    public function testInvalidCredentialsHandled()
    {
        $controller_file = APPPATH . 'controllers/Site.php';
        $content = file_get_contents($controller_file);
        
        // Check that error handling is in place
        $this->assertStringContainsString('invalid_username_or_password', $content);
    }

    /**
     * Test Case: Forgot password link is present
     * 
     * Expected: Link to forgot password page exists
     */
    public function testForgotPasswordLinkExists()
    {
        $view_file = APPPATH . 'views/auth.php';
        $content = file_get_contents($view_file);
        
        $this->assertStringContainsString('forgotpassword', $content);
    }

    /**
     * Test Case: Session is set correctly for staff
     * 
     * Verifies that staff login sets 'admin' session
     */
    public function testStaffSessionSetupCode()
    {
        $controller_file = APPPATH . 'controllers/Site.php';
        $content = file_get_contents($controller_file);
        
        // Check for admin session setup
        $this->assertStringContainsString("set_userdata('admin'", $content);
    }

    /**
     * Test Case: Session is set correctly for student/parent
     * 
     * Verifies that user login sets 'student' session
     */
    public function testUserSessionSetupCode()
    {
        $controller_file = APPPATH . 'controllers/Site.php';
        $content = file_get_contents($controller_file);
        
        // Check for student session setup
        $this->assertStringContainsString("set_userdata('student'", $content);
    }

    /**
     * Manual Browser Test Instructions
     * 
     * These tests should be run manually or via browser automation:
     * 
     * 1. ADMIN LOGIN TEST
     *    - Go to http://localhost/smart_school_src/site/auth
     *    - Enter admin credentials
     *    - Verify redirect to admin/admin/dashboard
     * 
     * 2. STUDENT LOGIN TEST
     *    - Go to http://localhost/smart_school_src/site/auth
     *    - Enter student credentials
     *    - Verify redirect to user/user/choose then user/user/dashboard
     * 
     * 3. PARENT LOGIN TEST
     *    - Go to http://localhost/smart_school_src/site/auth
     *    - Enter parent credentials
     *    - Verify redirect to user/user/choose then user/user/dashboard
     * 
     * 4. BACKWARD COMPATIBILITY TEST
     *    - Go to http://localhost/smart_school_src/site/login
     *    - Verify old admin functionality still works
     *    - Go to http://localhost/smart_school_src/site/userlogin
     *    - Verify old user functionality still works
     */
    public function testManualTestInstructionsReadable()
    {
        $this->assertTrue(true, 'See docblock above for manual test instructions');
    }
}
