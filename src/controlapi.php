<?php

/**
 * ControlAPI is a subset API which does all control operations. It is indicated by URL being: api.reqdc.com/tenant/control/*
 * These are static and usable by all tenants and all users and they can publish informatin, make information retrievable
 * retries, resyncs, data inserts and so forth
 *
 *
 * @author vehja
 *
 */
class ControlAPI
{

    /**
     *
     * @var MainAPI
     */
    public $mainAPI;

    /**
     * Operation can be like rerun
     *
     * @var string
     */
    public $operation;

    /**
     *
     * @var string
     */
    public $category;

    public $data;

    /**
     * All control api data comes in as json in all use cases.
     * Url only defines the tenant, category and operation
     * Some cases may use url parameters such as synchronicity on rerun, but that is handled by the called class, not here
     *
     * @throws GenException
     */
    public function main()
    {
        $this->category = mb_strtolower($this->mainAPI->urlPathPartsArray[3]); // Execution, implementation, request
        $this->operation = mb_strtolower($this->mainAPI->urlPathPartsArray[4]); // Rerun
        $this->data = json_decode(MainAPI::getPostData(), true);

        if ($this->category === 'execution') {
            $this->handleExecCategory();
        } elseif ($this->category === 'datastorage') {
            $this->handleDataStorageCategory();
        } elseif ($this->category === 'user') {
            $this->handleUserCategory();
        } elseif ($this->category === 'schedule') {
            $this->handleScheduleCategory();
        } else {
            throw new GenException('Valid control operation category must be provided');
        }
    }

    /**
     * Main level execution category branching
     *
     * @throws GenException
     */
    private function handleExecCategory()
    {
        if ($this->operation === 'rerun') {
            $this->handleExecRerun();
        } else {
            throw new GenException('Execution operation incorrect');
        }
    }

    /**
     * Rerun execution (which actually just grabs its request and uses it)
     *
     * example url:
     * /execution/rerun/[id]
     */
    private function handleExecRerun()
    {
        $id = su::strToObjectId($this->data['execid']);
        $exec = Execution::getById($id);
        $request = $exec->getRequest();
        $this->mainAPI->handleImplementation($request);
    }

    private function handleDataStorageCategory()
    {
        
        if ($this->operation === 'set') {
            $this->handleDataStorageSet();
        } else {
            throw new GenException('Datastorage operation incorrect');
        }
    }

    /**
     * Set a value to datastorage.
     * Upsert is by default true which means new value is inserted if it does not exist with category-key
     * combination
     *
     * example url:
     * /datastorage/[category]/[key]?type=string&upsert=false
     *
     * Body is value
     * {'value': 'jee','type':'xx','upsert':true}
     */
    private function handleDataStorageSet()
    {
        $category = $this->data['category'];
        $key = $this->data['key'];

        $type = $this->data['type'];
        $upsert = su::toBool($this->data['upsert']);
        $value = $this->data['value'];

        if (empty($value)) {
            DataStorage::delete($category, $key);
        } else {
            DataStorage::set($category, $key, $value, $upsert, $type);
        }
    }

    private function handleUserCategory()
    {
        if ($this->operation === 'passwordreset') {
            $this->handleUserResetPassword();
        } else {
            throw new GenException('User operation incorrect');
        }
    }

    /**
     * Password reset is aleways done through API, no view exists for it
     *
     * /user/passwordreset
     *
     * body must contain structure:
     * [{"name":"oldpassword","value":"xxx"},{"name":"password","value":"xxxx"},{"name":"repassword","value":"xxxx"}]
     *
     * @throws GenException
     */
    private function handleUserResetPassword()
    {
        $dat = $this->data;
        $oldPw = su::arraySearchMultidim($dat, 'name', 'oldpassword')['value'];
        $newPw = su::arraySearchMultidim($dat, 'name', 'password')['value'];
        $newPwre = su::arraySearchMultidim($dat, 'name', 'repassword')['value'];
        $user = session::getUser();
        if ($newPw !== $newPwre) {
            throw new GenException('New passwords must match');
        }

        if ($oldPw === $newPwre) {
            throw new GenException('Current password cannot be same as new password');
        }

        if (mb_strlen($newPw) < 8) {
            throw new GenException('New password must be at least 8 characters');
        }

        $user->authenticate($oldPw);

        $user->setPassword($newPw);
        
        log::info('User '. $user->getId().' password changed successfully');

        session::destroy();
        session::deleteAllForUser($user->getId());
    }

    private function handleScheduleCategory()
    {
        
        if ($this->operation === 'setcronschedule') {
            $this->handleScheduleSetCronSchedule();
        } else {
            throw new GenException('Schedule operation incorrect');
        }
    }

    private function handleScheduleSetCronSchedule()
    {
        $implementationId = $this->data['implementationId'];
        $cronExpr = $this->data['cronExpr'];

        if (empty($cronExpr)) {
            try {
                Schedule::deleteByImplementationIdCronScheduled($implementationId);
            } catch (NotFoundException $e) {
                log::warn('Couldnt delete schedule: ' . $e->getMessage());
            }
        } else {
            Schedule::setFromCronExpression($implementationId, $cronExpr);
        }
    }
}