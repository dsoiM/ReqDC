<?php

class UIImplementations extends MainUI
{

    function getName()
    {
        return 'Implementations';
    }

    function setViewVars()
    {
        $selection = su::gis($this->urlParts[2]);
        if ($selection) {
            return $this->renderOne($selection);
        } else {
            return $this->renderList();
        }
    }

    function renderOne($selectedImpl)
    {
        $impl = Implementation::getById($selectedImpl);

        return [
            'id' => $impl->getId(),
            'name' => $impl->getName(),
            'description' => $impl->getDescription()
        ];
    }

    function renderList()
    {
        $implArr = Session::getUser()->getAllowedImplementationIds();
        $x = [];
        foreach ($implArr as $i) {
            try {
                $impl = Implementation::getById($i);
            } catch (NotFoundException $e) {
                continue;
            }

            $x['listrows'][] = [
                'id' => $impl->getId(),
                'name' => $impl->getName(),
                'description' => $impl->getDescription()
            ];
        }
        return $x;
    }
}