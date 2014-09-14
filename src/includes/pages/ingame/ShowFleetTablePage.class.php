<?php

/**
 *  2Moons
 *  Copyright (C) 2012 Jan Kröpke
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package 2Moons
 * @author Jan Kröpke <info@2moons.cc>
 * @copyright 2012 Jan Kröpke <info@2moons.cc>
 * @license http://www.gnu.org/licenses/gpl.html GNU GPLv3 License
 * @version 2.0.0 (2015-01-01)
 * @info $Id: ShowFleetTablePage.class.php 2800 2013-10-04 22:07:04Z slaver7 $
 * @link http://2moons.cc/
 */


class ShowFleetTablePage extends AbstractGamePage
{
	public static $requireModule = MODULE_FLEET_TABLE;

    public function createACS($fleetId, $fleetData)
    {

		$rand 			= mt_rand(100000, 999999999);
		$acsName	 	= 'AG'.$rand;
		$acsCreator		= $this->user->id;

        $db = Database::get();
        $sql = "INSERT INTO %%AKS%% SET name = :acsName, ankunft = :time, target = :target;";
        $db->insert($sql, array(
            ':acsName'	=> $acsName,
            ':time'		=> $fleetData['fleet_start_time'],
			':target'	=> $fleetData['fleet_end_id']
        ));

        $acsID	= $db->lastInsertId();

        $sql = "INSERT INTO %%USERS_ACS%% SET acsID = :acsID, userID = :userID;";
        $db->insert($sql, array(
            ':acsID'	=> $acsID,
            ':userID'	=> $acsCreator
        ));

        $sql = "UPDATE %%FLEETS%% SET fleet_group = :acsID WHERE fleetId = :fleetId;";
        $db->update($sql, array(
            ':acsID'	=> $acsID,
            ':fleetId'	=> $fleetId
        ));

		return array(
			'name' 			=> $acsName,
			'id' 			=> $acsID,
		);
	}
	
	public function loadACS($fleetData) {

		$db = Database::get();
        $sql = "SELECT id, name FROM %%USERS_ACS%% INNER JOIN %%AKS%% ON acsID = id WHERE userID = :userID AND acsID = :acsID;";
        $acsResult = $db->selectSingle($sql, array(
            ':userID'   => $this->user->id,
            ':acsID'    => $fleetData['fleet_group']
        ));

		return $acsResult;
	}
	
	public function getACSPageData($fleetId)
	{

		$db = Database::get();

        $sql = "SELECT fleet_start_time, fleet_end_id, fleet_group, fleet_mess FROM %%FLEETS%% WHERE fleetId = :fleetId;";
        $fleetData = $db->selectSingle($sql, array(
            ':fleetId'  => $fleetId
        ));

        if ($db->rowCount() != 1)
			return array();

		if ($fleetData['fleet_mess'] == 1 || $fleetData['fleet_start_time'] <= TIMESTAMP)
			return array();
				
		if ($fleetData['fleet_group'] == 0)
			$acsData	= $this->createACS($fleetId, $fleetData);
		else
			$acsData	= $this->loadACS($fleetData);
	
		if (empty($acsData))
			return array();
			
		$acsName	= HTTP::_GP('acsName', '', UTF8_SUPPORT);
		if(!empty($acsName)) {
			if(PlayerUtil::isNameValid($acsName))
			{
				$this->sendJSON($this->lang['fl_acs_newname_alphanum']);
			}
			
			$sql = "UPDATE %%AKS%% SET name = acsName WHERE id = :acsID;";
            $db->update($sql, array(
                ':acsName'  => $acsName,
                ':acsID'    => $acsData['id']
            ));
            $this->sendJSON(false);
		}
		
		$invitedUsers	= array();

        $sql = "SELECT id, username FROM %%USERS_ACS%% INNER JOIN %%USERS%% ON userID = id WHERE acsID = :acsID;";
        $userResult = $db->select($sql, array(
            ':acsID'    => $acsData['id']
        ));

        foreach($userResult as $userRow)
		{
			$invitedUsers[$userRow['id']]	= $userRow['username'];
		}

		$newUser		= HTTP::_GP('username', '', UTF8_SUPPORT);
		$statusMessage	= "";
		if(!empty($newUser))
		{
			$sql = "SELECT id FROM %%USERS%% WHERE universe = :universe AND username = :username;";
            $newUserID = $db->selectSingle($sql, array(
                ':universe' => Universe::current(),
                ':username' => $newUser
            ), 'id');

            if(empty($newUserID)) {
				$statusMessage			= $this->lang['fl_player']." ".$newUser." ".$this->lang['fl_dont_exist'];
			} elseif(isset($invitedUsers[$newUserID])) {
				$statusMessage			= $this->lang['fl_player']." ".$newUser." ".$this->lang['fl_already_invited'];
			} else {
				$statusMessage			= $this->lang['fl_player']." ".$newUser." ".$this->lang['fl_add_to_attack'];
				
				$sql = "INSERT INTO %%USERS_ACS%% SET acsID = :acsID, userID = :newUserID;";
                $db->insert($sql, array(
                    ':acsID'        => $acsData['id'],
                    ':newUserID'    => $newUserID
                ));

                $invitedUsers[$newUserID]	= $newUser;
				
				$inviteTitle			= $this->lang['fl_acs_invitation_title'];
				$inviteMessage 			= $this->lang['fl_player'] . $this->user->username . $this->lang['fl_acs_invitation_message'];
				PlayerUtil::sendMessage($newUserID, $this->user->id, TIMESTAMP, 1, $this->user->username, $inviteTitle, $inviteMessage);
			}
		}
		
		return array(
			'invitedUsers'	=> $invitedUsers,
			'acsName'		=> $acsData['name'],
			'mainFleetID'	=> $fleetId,
			'statusMessage'	=> $statusMessage,
		);
	}
	
	public function show()
	{

		$acsData			= array();
		$FleetID			= HTTP::_GP('fleetId', 0);
		$GetAction			= HTTP::_GP('action', "");
		
		if(!empty($FleetID) && !IsVacationMode($USER))
		{
			switch($GetAction){
				case "sendfleetback":
					FleetUtil::SendFleetBack($USER, $FleetID);
				break;
				case "acs":
					$acsData	= $this->getACSPageData($FleetID);
				break;
			}
		}
		
		$techExpedition      = $this->user->getElement(124);

		if ($techExpedition >= 1)
		{
			$activeExpedition   = FleetUtil::getUsedSlots($this->user->id, 15, true);
			$maxExpedition 		= floor(sqrt($techExpedition));
		}
		else
		{
			$activeExpedition 	= 0;
			$maxExpedition 		= 0;
		}

		$maxFleetSlots	= FleetUtil::GetMaxFleetSlots($USER);

		$targetGalaxy	= HTTP::_GP('galaxy', (int) $this->planet->galaxy);
		$targetSystem	= HTTP::_GP('system', (int) $this->planet->system);
		$targetPlanet	= HTTP::_GP('planet', (int) $this->planet->planet);
		$targetType		= HTTP::_GP('planettype', (int) $this->planet->planet_type);
		$targetMission	= HTTP::_GP('target_mission', 0);

        $activeFleetSlots	= 0;

		$currentFleets		= FleetUtil::getCurrentFleetsByUserId($this->user->id);

		$FlyingFleetList	= array();
		
		foreach ($currentFleets as $fleetId => $fleetsRow)
		{
			if($fleetsRow['fleet_mission'] == 11) continue;

			$activeFleetSlots++;

			if($fleetsRow['fleet_mission'] == 4 && $fleetsRow['fleet_mess'] == FLEET_OUTWARD)
			{
				$returnTime	= $fleetsRow['fleet_start_time'];
			}
			else
			{
				$returnTime	= $fleetsRow['fleet_end_time'];
			}
			
			$FlyingFleetList[$fleetId]	= array(
				'mission'		=> $fleetsRow['fleet_mission'],
				'state'			=> $fleetsRow['fleet_mess'],
				'startGalaxy'	=> $fleetsRow['fleet_start_galaxy'],
				'startSystem'	=> $fleetsRow['fleet_start_system'],
				'startPlanet'	=> $fleetsRow['fleet_start_planet'],
				'startTime'		=> _date($this->lang['php_tdformat'], $fleetsRow['fleet_start_time'], $this->user->timezone),
				'endGalaxy'		=> $fleetsRow['fleet_end_galaxy'],
				'endSystem'		=> $fleetsRow['fleet_end_system'],
				'endPlanet'		=> $fleetsRow['fleet_end_planet'],
				'endTime'		=> _date($this->lang['php_tdformat'], $fleetsRow['fleet_end_time'], $this->user->timezone),
				'amount'		=> array_sum($fleetsRow['elements'][Vars::CLASS_FLEET]),
				'returntime'	=> $returnTime,
				'resttime'		=> $returnTime - TIMESTAMP,
				'FleetList'		=> $fleetsRow['elements'][Vars::CLASS_FLEET],
			);
		}
		
		$shipList	= array();
		
		foreach(Vars::getElements(Vars::CLASS_FLEET) as $elementId => $elementObj)
		{
			if ($PLANET[$elementObj->name] == 0) continue;
				
			$shipList[$elementId]	= array(
				'speed'	=> FleetUtil::GetFleetMaxSpeed($elementId, $USER),
				'count'	=> $PLANET[$elementObj->name],
			);
		}
		
		$this->assign(array(
			'shipList'		        => $shipList,
			'FlyingFleetList'		=> $FlyingFleetList,
			'activeExpedition'		=> $activeExpedition,
			'maxExpedition'			=> $maxExpedition,
			'activeFleetSlots'		=> $activeFleetSlots,
			'maxFleetSlots'			=> $maxFleetSlots,
			'targetGalaxy'			=> $targetGalaxy,
			'targetSystem'			=> $targetSystem,
			'targetPlanet'			=> $targetPlanet,
			'targetType'			=> $targetType,
			'targetMission'			=> $targetMission,
			'acsData'				=> $acsData,
			'isVacation'			=> IsVacationMode($USER),
			'bonusAttack'			=> PlayerUtil::getBonusValue(100, 'Attack', $USER),
			'bonusDefensive'		=> PlayerUtil::getBonusValue(100, 'Defensive', $USER),
			'bonusShield'			=> PlayerUtil::getBonusValue(100, 'Shield', $USER),
			'bonusCombustion'		=> $this->user->getElement(115) * 10,
			'bonusImpulse'			=> $this->user->getElement(117) * 20,
			'bonusHyperspace'		=> $this->user->getElement(118) * 30,
		));
		
		$this->display('page.fleetTable.default');
	}
}