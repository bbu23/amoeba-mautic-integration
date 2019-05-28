<?php

namespace MauticPlugin\MauticCrmBundle\Api;

use Mautic\PluginBundle\Exception\ApiErrorException;

/**
 * AmoebaCrm API.
 */
class AmoebacrmApi extends CrmApi {

  /**
   * Get Mautic fields to query.
   *
   * @param array $config
   *   The integration configuration.
   *
   * @return string
   *   Returns a string containing the fields to query with the table prefix.
   */
  public function getMauticLeadFieldString($config) {
    if (isset($config['leadFields'])) {
      $leadFields = implode(', l.', array_values($config['leadFields']));
      $mauticLeadFieldString = 'l.' . $leadFields;
    }
    return !empty($mauticLeadFieldString) ? $mauticLeadFieldString : '';
  }

  /**
   * Makes request to integration.
   *
   * @param string $url
   *   The request url.
   * @param array $parameters
   *   Request parameters.
   * @param string $method
   *   Request method.
   * @param array $settings
   *   The request settings.
   *
   * @return mixed|string
   *   Returns request response.
   *
   * @throws ApiErrorException
   *   Throws error if the request is unsuccessful.
   */
  public function request($url, $parameters = [], $method = 'GET', $settings = []) {
    $response = $this->integration->makeRequest($url, $parameters, $method, $settings);
    // Check the response and throw errors if the request is not successfully done.
    if (is_object($response)) {
      if (!in_array($response->code, [200, 201, 202])) {
        $message = $this->integration->parseCallbackResponse($response->body, !empty($settings['authorize_session']));
        throw new ApiErrorException($message['message']);
      }
      else {
        return $this->integration->parseCallbackResponse($response->body, !empty($settings['authorize_session']));
      }
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFieldsForPush($fields) {
    $addressFields = [
      'country_code',
      'administrative_area',
      'locality',
      'postal_code',
      'address_line1',
      'address_line2',
    ];
    $fieldsMapping = [];
    foreach ($fields as $key => $fieldValue) {
      if (!in_array($key, $addressFields)) {
        $fieldsMapping[$key] = ['value' => $fieldValue];
      }
      else {
        $fieldsMapping['address']['value'][$key] = ($key == 'country_code') ? $this->getCountryCode($fieldValue) : $fieldValue;;
      }
    }
    return $fieldsMapping;
  }

  /**
   * Returns the country code of the specified country.
   *
   * @param string $country
   *   The name of the country.
   *
   * @return string|false
   *   Returns the country code if found, otherwise returns FALSE.
   */
  public function getCountryCode($country) {
    $countryCodes = [
      'Afghanistan' => 'AF',
      'Åland Islands' => 'AX',
      'Albania' => 'AL',
      'Algeria'=> 'DZ',
      'American Samoa' => 'AS',
      'Andorra' => 'AD',
      'Angola' => 'AO',
      'Anguilla' => 'AI',
      'Antarctica' => 'AQ',
      'Antigua and Barbuda' => 'AG',
      'Argentina' => 'AR',
      'Australia' => 'AU',
      'Austria' => 'AT',
      'Azerbaijan' => 'AZ',
      'Bahamas' => 'BS',
      'Bahrain' => 'BH',
      'Bangladesh' => 'BD',
      'Barbados' => 'BB',
      'Belarus' => 'BY',
      'Belgium' => 'BE',
      'Belize' => 'BZ',
      'Benin' => 'BJ',
      'Bermuda' => 'BM',
      'Bhutan' => 'BT',
      'Bolivia' => 'BO',
      'Bosnia and Herzegovina' => 'BA',
      'Botswana' => 'BW',
      'Bouvet Island' => 'BV',
      'Brazil' => 'BR',
      'British Indian Ocean Territory' => 'IO',
      'Brunei Darussalam' => 'BN',
      'Bulgaria' => 'BG',
      'Burkina Faso' => 'BF',
      'Burundi' => 'BI',
      'Cambodia' => 'KH',
      'Cameroon' => 'CM',
      'Canada' => 'CA',
      'Cape Verde' => 'CV',
      'Cayman Islands' => 'KY',
      'Central African Republic' => 'CF',
      'Chad' => 'TD',
      'Chile' => 'CL',
      'China' => 'CN',
      'Christmas Island' => 'CX',
      'Cocos (Keeling) Islands' => 'CC',
      'Colombia' => 'CO',
      'Comoros' => 'KM',
      'Congo' => 'CG',
      'Zaire' => 'CD',
      'Cook Islands' => 'CK',
      'Costa Rica' => 'CR',
      'Côte D\'Ivoire' => 'CI',
      'Croatia' => 'HR',
      'Cuba' => 'CU',
      'Cyprus' => 'CY',
      'Czech Republic' => 'CZ',
      'Denmark' => 'DK',
      'Djibouti' => 'DJ',
      'Dominica' => 'DM',
      'Dominican Republic' => 'DO',
      'Ecuador' => 'EC',
      'Egypt' => 'EG',
      'El Salvador' => 'SV',
      'Equatorial Guinea' => 'GQ',
      'Eritrea' => 'ER',
      'Estonia' => 'EE',
      'Ethiopia' => 'ET',
      'Falkland Islands (Malvinas)' => 'FK',
      'Faroe Islands' => 'FO',
      'Fiji' => 'FJ',
      'Finland' => 'FI',
      'France' => 'FR',
      'French Guiana' => 'GF',
      'French Polynesia' => 'PF',
      'French Southern Territories' => 'TF',
      'Gabon' => 'GA',
      'Gambia' => 'GM',
      'Georgia' => 'GE',
      'Germany' => 'DE',
      'Ghana' => 'GH',
      'Gibraltar' => 'GI',
      'Greece' => 'GR',
      'Greenland' => 'GL',
      'Grenada' => 'GD',
      'Guadeloupe' => 'GP',
      'Guam' => 'GU',
      'Guatemala' => 'GT',
      'Guernsey' => 'GG',
      'Guinea' => 'GN',
      'Guinea-Bissau' => 'GW',
      'Guyana' => 'GY',
      'Haiti' => 'HT',
      'Heard Island and Mcdonald Islands' => 'HM',
      'Vatican City State' => 'VA',
      'Honduras' => 'HN',
      'Hong Kong' => 'HK',
      'Hungary' => 'HU',
      'Iceland' => 'IS',
      'India' => 'IN',
      'Indonesia' => 'ID',
      'Iran, Islamic Republic of' => 'IR',
      'Iraq' => 'IQ',
      'Ireland' => 'IE',
      'Isle of Man' => 'IM',
      'Israel' => 'IL',
      'Italy' => 'IT',
      'Jamaica' => 'JM',
      'Japan' => 'JP',
      'Jersey' => 'JE',
      'Jordan' => 'JO',
      'Kazakhstan' => 'KZ',
      'KENYA' => 'KE',
      'Kiribati' => 'KI',
      'Korea, Democratic People\'s Republic of' => 'KP',
      'Korea, Republic of' => 'KR',
      'Kuwait' => 'KW',
      'Kyrgyzstan' => 'KG',
      'Lao People\'s Democratic Republic' => 'LA',
      'Latvia' => 'LV',
      'Lebanon' => 'LB',
      'Lesotho' => 'LS',
      'Liberia' => 'LR',
      'Libyan Arab Jamahiriya' => 'LY',
      'Liechtenstein' => 'LI',
      'Lithuania' => 'LT',
      'Luxembourg' => 'LU',
      'Macao' => 'MO',
      'Macedonia, the Former Yugoslav Republic of' => 'MK',
      'Madagascar' => 'MG',
      'Malawi' => 'MW',
      'Malaysia' => 'MY',
      'Maldives' => 'MV',
      'Mali' => 'ML',
      'Malta' => 'MT',
      'Marshall Islands' => 'MH',
      'Martinique' => 'MQ',
      'Mauritania' => 'MR',
      'Mauritius' => 'MU',
      'Mayotte' => 'YT',
      'Mexico' => 'MX',
      'Micronesia, Federated States of' => 'FM',
      'Moldova, Republic of' => 'MD',
      'Monaco' => 'MC',
      'Mongolia' => 'MN',
      'Montenegro' => 'ME',
      'Montserrat' => 'MS',
      'Morocco' => 'MA',
      'Mozambique' => 'MZ',
      'Myanmar' => 'MM',
      'Namibia' => 'NA',
      'Nauru' => 'NR',
      'Nepal' => 'NP',
      'Netherlands' => 'NL',
      'Netherlands Antilles' => 'AN',
      'New Caledonia' => 'NC',
      'New Zealand' => 'NZ',
      'Nicaragua' => 'NI',
      'Niger' => 'NE',
      'Nigeria' => 'NG',
      'Niue' => 'NU',
      'Norfolk Island' => 'NF',
      'Northern Mariana Islands' => 'MP',
      'Norway' => 'NO',
      'Oman' => 'OM',
      'Pakistan' => 'PK',
      'Palau' => 'PW',
      'Palestinian Territory, Occupied' => 'PS',
      'Panama' => 'PA',
      'Papua New Guinea' => 'PG',
      'Paraguay' => 'PY',
      'Peru' => 'PE',
      'Philippines' => 'PH',
      'Pitcairn' => 'PN',
      'Poland' => 'PL',
      'Portugal' => 'PT',
      'Puerto Rico' => 'PR',
      'Qatar' => 'QA',
      'Réunion' => 'RE',
      'Romania' => 'RO',
      'Russian Federation' => 'RU',
      'Rwanda' => 'RW',
      'Saint Helena' => 'SH',
      'Saint Kitts and Nevis' => 'KN',
      'Saint Lucia' => 'LC',
      'Saint Pierre and Miquelon' => 'PM',
      'Saint Vincent and the Grenadines' => 'VC',
      'Samoa' => 'WS',
      'San Marino' => 'SM',
      'Sao Tome and Principe' => 'ST',
      'Saudi Arabia' => 'SA',
      'Senegal' => 'SN',
      'Serbia' => 'RS',
      'Seychelles' => 'SC',
      'Sierra Leone' => 'SL',
      'Singapore' => 'SG',
      'Slovakia' => 'SK',
      'Slovenia' => 'SI',
      'Solomon Islands' => 'SB',
      'Somalia' => 'SO',
      'South Africa' => 'ZA',
      'South Georgia and the South Sandwich Islands' => 'GS',
      'Spain' => 'ES',
      'Sri Lanka' => 'LK',
      'Sudan' => 'SD',
      'Suriname' => 'SR',
      'Svalbard and Jan Mayen' => 'SJ',
      'Swaziland' => 'SZ',
      'Sweden' => 'SE',
      'Switzerland' => 'CH',
      'Syrian Arab Republic' => 'SY',
      'Taiwan, Province of China' => 'TW',
      'Tajikistan' => 'TJ',
      'Tanzania, United Republic of' => 'TZ',
      'Thailand' => 'TH',
      'Timor-Leste' => 'TL',
      'Togo' => 'TG',
      'Tokelau' => 'TK',
      'Tonga' => 'TO',
      'Trinidad and Tobago' => 'TT',
      'Tunisia' => 'TN',
      'Turkey' => 'TR',
      'Turkmenistan' => 'TM',
      'Turks and Caicos Islands' => 'TC',
      'Tuvalu' => 'TV',
      'Uganda' => 'UG',
      'Ukraine' => 'UA',
      'United Arab Emirates' => 'AE',
      'United Kingdom' => 'GB',
      'United States' => 'US',
      'United States Minor Outlying Islands' => 'UM',
      'Uruguay' => 'UY',
      'Uzbekistan' => 'UZ',
      'Vanuatu' => 'VU',
      'Venezuela' => 'VE',
      'Viet Nam' => 'VN',
      'Virgin Islands, British' => 'VG',
      'Virgin Islands, U.S.' => 'VI',
      'Wallis and Futuna' => 'WF',
      'Western Sahara' => 'EH',
      'Yemen' => 'YE',
      'Zambia' => 'ZM',
      'Zimbabwe' => 'ZW',
    ];
    return isset($countryCodes[$country]) ? $countryCodes[$country] : FALSE;
  }

}

