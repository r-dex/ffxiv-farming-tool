<?php
    namespace Rosaworks\FarmingTool;

    class CharacterGroup
    {
        public $ID;
        public $Name;
        public $Members;

        public function __construct($CharacterGroup)
        {
            if (is_object($CharacterGroup)) {
                foreach ($CharacterGroup as $Key => $Value) {
                    $this->{$Key} = $Value;
                }
            }
        }

        public function setMembers($MemberList, $Mounts)
        {
            $Response = false;
            if (is_array($MemberList)) {
                $Response = true;
                foreach ($MemberList as $Index => $Member) {
                    $Member = new Character($Member->ID, $Member->Name);
                    $Member->setMounts($Mounts[$Index]);
                    $this->Members[] = $Member;
                }
            }
            return $Response;
        }
    }