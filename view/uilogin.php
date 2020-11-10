<?php

class UILogin extends MainUI
{

    function getName()
    {
        return 'Login';
    }

    function setViewVars()
    {
        $email = su::getRequestValue('email');
        $m = [];
        // If user is authenticated and user in login screen
        $user = Session::getUser();
        if (!empty($user) && $user->isAuthenticated() === true ) {
            header('Location: ' . '/' . self::UIURLPREFIX);
            return;
        }
        
        if (!User::doUsersExist()) {
            log::warn('No users, running empty db init');
            db::initializeDB();
            return ['message' => 'Empty database initialized. Login with: mightyadmin@reqdc.local // asdf'];
        }
        
        try {

            if (!$user && $email) {
                Log::debug('User logging in! '. $email);
                
                $user = User::getById($email);
                $user->authenticate(su::getRequestValue('password'));
                $tenantId = $user->getAllowedTenantIds()[0];
                
                Session::startSession(true);
                $user->selectTenantAndSaveToSession($tenantId);
                if (!empty(su::getRequestValue('r'))) {
                    header('Location: ' . config::get('UIURL') . su::getRequestValue('r'));
                } else {
                    header('Location: ' . '/' . self::UIURLPREFIX);
                }
                
                //TODO: make front send browser timezone here, save it to session and use that timezone always when rendering frontend timestamps
            }
        } catch (InvalidCredentialsException $e) {
            Log::error($email.' : '.$e->getMessage());
            $m =  ['message' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::error($email.' : '.$e);
            $m =  ['message' => 'userId or password incorrect '];
        }
        return ["r"=>trim(su::getRequestValue("r")) ]+$m;
    }
}