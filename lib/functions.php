<?php

function birthdays_get_available_profile_fields() {
	$result = false;

	$profile_fields = elgg_get_config("profile_fields");

	if (!empty($profile_fields) && is_array($profile_fields)) {
		$found_fields = array();

		foreach ($profile_fields as $metadata_name => $type) {
			if (in_array($type, array("date", "birthday"))) {
				$found_fields[$metadata_name] = $type;
			}
		}

		if (!empty($found_fields)) {
			$result = $found_fields;
		}
	}

	return $result;
}

function birthdays_get_configured_birthday_field() {
	static $result;

	if (!isset($result)) {
		$result = false;

		if ($setting = elgg_get_plugin_setting("birthday_field", "birthdays")) {
			$result = $setting;
		}
	}

	return $result;
}

function birthdays_get_configered_interval() {
	$setting = elgg_get_plugin_setting("interval", "birthdays");
	if ($setting) {
		return (int) $setting;
	} else {
		return 14;
	}
}

function birthdays_get_upcoming_user_guids() {
	$site = elgg_get_site_entity();
	$today = (int) date("z") + 1;
	$field = birthdays_get_configured_birthday_field();

	if (!$field) {
		return false;
	}

	if (date("w") == 1) { // Mondays
		$today -= 2;
	} elseif (date("w") == 2) { // Tuesdays
		$today -= 1;
	}

	$dbprefix = elgg_get_config('dbprefix');
	$field = mysql_real_escape_string($field);

	$sql = "SELECT
		e.guid,
		DAYOFYEAR(DATE(msv.string)) AS birthday
		FROM {$dbprefix}entities e
		JOIN {$dbprefix}entity_relationships r ON r.guid_one = e.guid
		JOIN {$dbprefix}metadata m ON e.guid = m.entity_guid
		JOIN {$dbprefix}metastrings msn ON m.name_id = msn.id
		JOIN {$dbprefix}metastrings msv ON m.value_id = msv.id
		WHERE
		e.type = 'user' AND
		r.relationship = 'member_of_site' AND
		r.guid_two = {$site->guid} AND
		msn.string = '{$field}'
		HAVING birthday >= $today
		ORDER BY birthday
		LIMIT 25";

	$users = get_data($sql);

	$return = array();
	foreach ($users as $user) {
		$return[] = $user->guid;
	}

	return $return;
}

function birthdays_get_upcoming_users($limit = 10) {
	if (!birthdays_get_configured_birthday_field()) {
		return false;
	}

	$limit = (int) $limit;
	$user_guids = birthdays_get_upcoming_user_guids();

	if ($user_guids) {
		$users = elgg_get_entities(array(
			'guids' => $user_guids,
			'type' => 'user',
			'limit' => $limit,
			'order_by' => null
		));

		// order on birthday
		$order = array_flip($user_guids);
		usort($users, function($a, $b) use ($order) {
			return ($order[$a->guid] < $order[$b->guid]) ? -1 : 1;
		});

		if ($limit < 10) {
			return array_slice($users, 0, $limit);
		} else {
			return $users;
		}
	} else {
		return array();
	}
}
