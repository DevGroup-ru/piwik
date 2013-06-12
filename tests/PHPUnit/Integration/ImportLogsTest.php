<?php
/**
 * Piwik - Open source web analytics
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Tests the log importer.
 */
class Test_Piwik_Integration_ImportLogs extends IntegrationTestCase
{
    public static $fixture = null; // initialized below class definition

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        
        foreach (self::getSegmentsToPreArchive() as $idx => $segment) {
            $idSite = false;
            $autoArchive = true;
            $enabledAllUsers = true;
            
            if ($idx == 0) {
                $idSite = self::$fixture->idSite; // for first segment only archive for first site
            } else if ($idx == 1) {
                $autoArchive = false; // for second segment only archive for second site
            } else if ($idx == 2) {
                $enabledAllUsers = false; // for third segment enable for superuser only
            }
            
            Piwik_SegmentEditor_API::getInstance()->add(
                'segment'.$idx, $segment, $idSite, $autoArchive, $enabledAllUsers);
        }
    }
    
    /**
     * @dataProvider getApiForTesting
     * @group        Integration
     * @group        ImportLogs
     */
    public function testApi($api, $params)
    {
        $this->runApiTests($api, $params);
    }

    public function getApiForTesting()
    {
        return array(
            array('all', array('idSite'  => self::$fixture->idSite,
                               'date'    => '2012-08-09',
                               'periods' => 'month')),

            // report generated from custom log format including generation time
            array('Actions.getPageUrls', array('idSite'  => self::$fixture->idSite,
                                               'date'    => '2012-09-30',
                                               'periods' => 'day')),

            array('VisitsSummary.get', array('idSite'     => self::$fixture->idSite2,
                                             'date'       => '2012-08-09',
                                             'periods'    => 'month',
                                             'testSuffix' => '_siteIdTwo_TrackedUsingLogReplay')),
        );
    }
    
    public static function getSegmentsToPreArchive()
    {
        return array(
            'browserCode==IE',
            'customVariableName1==Not-bot',
            'customVariablePageName1=='.urlencode('HTTP-code')
        );
    }
    
    public function getApiForCronTest()
    {
        $results = array();
        $results[] = array('VisitsSummary.get', array('idSite'  => 'all',
                                                      'date'    => '2012-08-09',
                                                      'periods' => array('day', 'week', 'month', 'year')));

        foreach (self::getSegmentsToPreArchive() as $idx => $segment) {
            $results[] = array('VisitsSummary.get', array('idSite'     => 'all',
                                                          'date'       => '2012-08-09',
                                                          'periods'    => array('day', 'week', 'month', 'year'),
                                                          'segment'    => $segment,
                                                          'testSuffix' => '_segment'.$idx));
        }
        
        $results[] = array('VisitsSummary.get', array('idSite'     => 'all',
                                                      'date'       => '2012-08-09',
                                                      'periods'    => array('day', 'week', 'month', 'year'),
                                                      'segment'    => 'browserCode==EP',
                                                      'testSuffix' => '_nonPreArchivedSegment'));
        
        return $results;
    }
    
    public function getArchivePhpCronOptionsToTest()
    {
        return array(
            array('noOptions', array()),
            // segment archiving makes calling the script more than once impractical. if all 4 are
            // called, this test can take up to 13min to complete.
            /*array('forceAllWebsites', array('--force-all-websites' => false)),
            array('forceAllPeriods_lastDay', array('--force-all-periods' => '86400')),
            array('forceAllPeriods_allTime', array('--force-all-periods' => false)),*/
        );
    }
    
    /**
     * @dataProvider getArchivePhpCronOptionsToTest
     * @group        Integration
     * @group        ImportLogs
     */
    public function testArchivePhpCron($optionGroupName, $archivePhpOptions)
    {
        self::deleteArchiveTables();
        $this->setLastRunArchiveOptions();
        $this->runArchivePhpCron($archivePhpOptions);
        
        foreach ($this->getApiForCronTest() as $testInfo) {
            list($api, $params) = $testInfo;
            
            if (!isset($params['testSuffix'])) {
                $params['testSuffix'] = '';
            }
            $params['testSuffix'] .= '_archiveCron_' . $optionGroupName;
            $params['disableArchiving'] = true;
            
            $this->runApiTests($api, $params);
        }
    }

    /**
     * @group        Integration
     * @group        ImportLogs
     * 
     * NOTE: This test must be last since the new sites that get added are added in
     *       random order.
     */
    public function testDynamicResolverSitesCreated()
    {
        self::$fixture->logVisitsWithDynamicResolver();

        // reload access so new sites are viewable
        Zend_Registry::get('access')->setSuperUser(true);

        // make sure sites aren't created twice
        $piwikDotNet = Piwik_SitesManager_API::getInstance()->getSitesIdFromSiteUrl('http://piwik.net');
        $this->assertEquals(1, count($piwikDotNet));

        $anothersiteDotCom = Piwik_SitesManager_API::getInstance()->getSitesIdFromSiteUrl('http://anothersite.com');
        $this->assertEquals(1, count($anothersiteDotCom));

        $whateverDotCom = Piwik_SitesManager_API::getInstance()->getSitesIdFromSiteUrl('http://whatever.com');
        $this->assertEquals(1, count($whateverDotCom));
    }

    public function getOutputPrefix()
    {
        return 'ImportLogs';
    }
    
    private function setLastRunArchiveOptions()
    {
        $periodTypes = array('day', 'periods');
        $idSites = Piwik_SitesManager_API::getInstance()->getAllSitesId();
        
        $time = Piwik_Date::factory(self::$fixture->dateTime)->subDay(1)->getTimestamp();
        
        foreach ($periodTypes as $period) {
            foreach ($idSites as $idSite) {
                $lastRunArchiveOption = "lastRunArchive" . $period . "_" . $idSite;
                
                Piwik_SetOption($lastRunArchiveOption, $time);
            }
        }
    }
    
    private function runArchivePhpCron($options)
    {
        $archivePhpScript = PIWIK_INCLUDE_PATH . '/tests/PHPUnit/proxy/archive.php';
        $urlToProxy = Test_Piwik_BaseFixture::getRootUrl() . 'tests/PHPUnit/proxy/index.php';
        
        // create the command
        $cmd = "php \"$archivePhpScript\" --url=\"$urlToProxy\" ";
        foreach ($options as $name => $value) {
            $cmd .= $name;
            if ($value !== false) {
                $cmd .= '="' . $value . '"';
            }
            $cmd .= ' ';
        }
        $cmd .= '2>&1';
        
        // run the command
        exec($cmd, $output, $result);
        if ($result !== 0) {
            throw new Exception("log importer failed: " . implode("\n", $output) . "\n\ncommand used: $cmd");
        }

        return $output;
    }
}

Test_Piwik_Integration_ImportLogs::$fixture = new Test_Piwik_Fixture_ManySitesImportedLogs();

