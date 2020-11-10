<?php

abstract class MainUI
{

    // Prefix, can be ui/
    const UIURLPREFIX = "";

    // Index of fragments where to look for actual view name
    const UIFRAG = 1;

    public $twig;

    /**
     *
     * @var string
     */
    public $viewname;

    /**
     *
     * @var array
     */
    public $viewvars = [];

    public $urlParts;

    /**
     *
     * @var User
     */
    public $user;
   
    public $errorPageRenderable = false;

    public static function handle()
    {
        try {
            ob_start();
            
            header('X-Powered-By: ReqDC Platform');
            
            Session::startSession();
            $uri = $_SERVER['REQUEST_URI'];
            log::info('UI: '. $uri);
            $urlParts = su::getUrlParts($uri);
            $viewname = su::gis($urlParts[self::UIFRAG]) ?: 'home';

            // IF user has not authenticated and user is not in login view
            $user = Session::getUser();
            self::checkUserLoginStatus($user,$viewname,$uri);
            
            $view = self::loadUIname($viewname);
            $view->user = $user;

            $view->urlParts = $urlParts;
            $loader = new \Twig\Loader\FilesystemLoader(config::get('UIPATH'));
            $view->twig = new \Twig\Environment($loader, []);
            $view->setGenericVars();
            $view->errorPageRenderable = true;
            //Viewvars are brought to twig
            $view->viewvars = array_merge($view->viewvars,$view->setViewVars() ?: []);
            
            $view->render();
        } catch (NotFoundException $e) {
            http_response_code(404);
            Log::error($e);
            self::renderErrorPage($view,$e->getMessage());
        } catch (GenException $e) {
            http_response_code(501);
            Log::fatal($e);
            self::renderErrorPage($view,$e->getMessage());
        } catch (Throwable $e) {
            http_response_code(500);
            Log::fatal($e);
            Mail::sendAlertFromException($e);
            $msg = config::get('ENV') === 'local' ? $e : 'Server error occurred. Please try again or contact ReqDC support';
            self::renderErrorPage($view,$msg);
        }
        
        
    }
    
    private static function renderErrorPage(MainUI $view,$message) {
        
        if ($view->errorPageRenderable === true) {
            $view->viewvars['viewfilename'] = 'errorpage.html.twig';
            $view->viewvars['errorPageMessage'] = $message;
            $view->render();
        } else {
            self::echoOutput("<pre>" . htmlentities($message) . "</pre>");
        }
        
    }
    
    private static function checkUserLoginStatus($user,$viewname,$uri) {
        if ((empty($user) || $user->isAuthenticated() !== true) && $viewname !== 'login') {
            header('Location: ' . '/' . self::UIURLPREFIX . 'login?r='.urlencode(trim($uri)));
            session::destroy();
            exit();
        }
                
    }

    
    private function echoOutput($response) {
        
        $ob = su::endOB();
        
        if (Config::get('ENV') === 'local') {
            echo $ob;
        }
        
        echo $response;
    }

    /**
     *
     * @param string $viewname
     * @throws NotFoundException
     * @return MainUI
     */
    public static function loadUIname($viewname)
    {
        $viewClassName = 'UI' . $viewname;
        $viewFilePath = 'view/' . strtolower($viewClassName) . '.php';

        if (file_exists($viewFilePath)) {
            include_once $viewFilePath;
        } else {
            throw new NotFoundException('View ' . $viewname . ' not found');
        }
        $implClasses = @class_parents($viewClassName);
        if (! $implClasses || ! in_array('MainUI', $implClasses)) {
            throw new GenException('View ' . $viewname . ' not allowed');
        }
        return new $viewClassName();
    }

    /**
     * Set values that can be used by all views
     */
    public function setGenericVars()
    {
        $this->viewvars = [
            'viewname' => $this->getName(),
            'viewclassname' => get_class($this),
            'viewfilename' => strtolower(get_class($this)) . '.html.twig',
            'uiurlprefix' => self::UIURLPREFIX,
            'UIURL' => config::get('UIURL'),
            'APIURL' => config::get('APIURL'),
            'MAINURL' => config::get('MAINURL'),
            'datatypes' => DataStorage::TYPES,
            'verRef' => su::getVersionRef()
        ];

        if (Session::getUser()) {
            // bring everything from user session
            $this->viewvars['userviewvars'] = Session::getUserViewVars();
            $this->viewvars['CSRFTOKEN'] = Session::getAndStoreFreshCSRFTokenForIndex();
        }
    }

    /**
     * Print out via twig template using vars
     */
    private function render()
    {
        self::echoOutput($this->twig->render('index.html.twig', $this->viewvars));
    }

    /**
     * 
     * @return array|void
     */
    abstract function setViewVars() ;

    abstract function getName();

    public function getDatepickerFields()
    {
        $data = [];
        $data['dataBeginDate'] = su::getRequestValue('beginDate', '7 days ago');
        $data['dataEndDate'] = su::getRequestValue('endDate', 'now');
        $data['statusFilter'] = su::getRequestValue('statusFilter', null);
        return $data;
    }
}