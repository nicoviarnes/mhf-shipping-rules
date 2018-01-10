function calculateshipping($DATA) {

	/* do not edit above this line */

	$_RATES = array();

	$isSummer = false;

	//free shipping is applied automatically from friday midnight through to sunday midnight, i.e. free all weekend
	//free shipping applies to non perishables only
	$enableFreeShippingWeekends = false;
	
	//states which alcohol can't be shipped to
	$alcoholFreeStates = array('AL','AK','AR','CT','DE','FL','HI','ID','MA','MS','NH','ND','OK','PA','RI','SD','UT','VT','WV');

	$myRates = array(
		'FedEx Ground Shipping' => array(
			75 => 12,
			150 => 15,
			250 => 18,
			99999999 => 0,
		),
		'FedEx 2nd Day Air' => array(
			75 => 28,
			150 => 36,
			250 => 44,
			500 => 75,
			1000 => 150,
			99999999 => 200,
		),
		'FedEx Overnight' => array(
			75 => 45,
			150 => 60,
			250 => 85,
			500 => 150,
			1000 => 250,
			99999999 => 350,
		),
		'USPS Priority Mail' => array(
			25 => 15,
			50 => 17,
			75 => 19,
			100 => 21,
			150 => 23,
			200 => 25,
			250 => 27,
			300 => 30,
		),
	);

	if ($DATA['destination']['country'] != 'US') return array();

	$DATA = enrichProductDetails($DATA);

	date_default_timezone_set('America/Los_Angeles');
	$dow = date('N');

	$freeShippingInEffect = false;
	if ($enableFreeShippingWeekends && ($dow == 6 || $dow == 7)) $freeShippingInEffect = true;

	$tFree = 0;
	$t = 0;
	$maxTransitDays = 999;
	$hasAlcohol = false;
	foreach ($DATA['items'] as $item) {
		$thisItemMaxTransitDays = 999;
		$shippingGroup = 'non-perishables';
		if (isset($item['tags']) && in_array('Perishable',$item['tags'])) {
			$shippingGroup = 'perishables';
			$thisItemMaxTransitDays = 2;
			if ($isSummer) $thisItemMaxTransitDays = 1;
		} else if (isset($item['tags']) && (in_array('Fresh Pasta',$item['tags']) || in_array('FreshPasta',$item['tags']))) {
			$shippingGroup = 'fresh pasta';
			$thisItemMaxTransitDays = 1;
		} else if (isset($item['tags']) && in_array('Chocolate',$item['tags'])) {
			$shippingGroup = 'chocolate';
			if ($isSummer) $thisItemMaxTransitDays = 2;
		} else if (isset($item['tags']) && in_array('Alcohol',$item['tags'])) {
			$shippingGroup = 'alcohol';
			$hasAlcohol = true;
		} else if (isset($item['tags']) && in_array('Perishable-Alcohol',$item['tags'])) {
			$shippingGroup = 'perishables-alcohol';
			$hasAlcohol = true;
			$thisItemMaxTransitDays = 2;
		} else if (isset($item['tags']) && in_array('Virtual',$item['tags'])) {
			//ignore this
			continue;
		}
		if ($freeShippingInEffect && $shippingGroup == 'non-perishables') {
			//item is free, do not count towards total
		} else {
			$tFree += $item['quantity']*$item['price']/100;
		}
		$t += $item['quantity']*$item['price']/100;
		if ($thisItemMaxTransitDays < $maxTransitDays) $maxTransitDays = $thisItemMaxTransitDays;
	}
	
	//check alcohol
	if ($hasAlcohol && in_array($DATA['destination']['province'],$alcoholFreeStates)) {
    	$_RATES[] = array(
			"service_name" => 'For the love of freshness, we cannot ship alcohol to your destination.',
			"service_code" => 'CUSTOM_ERR_MSG',
			"total_price" => 0,
			"currency" => "USD",
		);
		return $_RATES;
	}

	$isPOBox = false;
	$addressline = $DATA['destination']['address1'].' '.$DATA['destination']['address2'].' '.$DATA['destination']['address3'];
	if (preg_match('/(p\.?o\.? ?box)|(post ?office box)/i',$addressline)) {
		$isPOBox = true;
	}

	//apply shipping method restrictions
	if ($maxTransitDays == 2) {
		$myRates['FedEx Ground Shipping'] = null;
		$myRates['USPS Priority Mail'] = null;
	} else if ($maxTransitDays == 1) {
		$myRates['FedEx Ground Shipping'] = null;
		$myRates['FedEx 2nd Day Air'] = null;
		$myRates['USPS Priority Mail'] = null;
	}
	if (!($isPOBox || $DATA['destination']['province'] == 'AK' || $DATA['destination']['province'] == 'HI')) {
		$myRates['USPS Priority Mail'] = null;
	} else {
		$myRates['FedEx Ground Shipping'] = null;
		$myRates['FedEx 2nd Day Air'] = null;
		$myRates['FedEx Overnight'] = null;
		
		if ($maxTransitDays <= 2) {
			//trigger custom message
			$_RATES[] = array(
				"service_name" => 'For the love of freshness, we cannot ship perishables to Alaska, Hawaii and PO Boxes.',
				"service_code" => 'CUSTOM_ERR_MSG',
				"total_price" => 0, //really big number to deter customer from selecting if it isn't hidden automatically
				"currency" => "USD",
			);
		}
	}

	foreach ($myRates as $rateName => $r) {
		if ($r === null) continue;
		foreach ($r as $mx => $p) {
			if ($rateName == 'FedEx Ground Shipping' && $tFree == 0) {
				$rateName = 'Free FedEx Ground Shipping';
				$_RATES[] = array(
					"service_name" => $rateName,
					"service_code" => $rateName,
					"total_price" => 0,
					"currency" => "USD",
				);
				break;
			} else {
				if ($t <= $mx) {
					$_RATES[] = array(
						"service_name" => $rateName,
						"service_code" => $rateName,
						"total_price" => $p*100,
						"currency" => "USD",
					);
					break;
				}
			}
		}
	}

	return $_RATES;
