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
    
    public function getSegmentsToPreArchive()
    {
        return array(
            'browserCode==IE',
            'userCountry==JP',
            'customVariableName1==HTTP-code'
        );
    }
    
    public function getApiForCronTest()
    {
        $results = array();
        $results[] = array('VisitsSummary.get', array('idSite'  => self::$fixture->idSite, // TODO: change to 'all'
                                                      'date'    => '2012-08-09',
                                                      'periods' => array('day', 'week', 'month', 'year')));

        foreach ($this->getSegmentsToPreArchive() as $idx => $segment) {
            $results[] = array('VisitsSummary.get', array('idSite'     => self::$fixture->idSite,
                                                          'date'       => '2012-08-09',
                                                          'periods'    => array('day', 'week', 'month', 'year'),
                                                          'segment'    => $segment,
                                                          'testSuffix' => '_segment'.$idx));
        }
        
        $results[] = array('VisitsSummary.get', array('idSite'     => self::$fixture->idSite,
                                                      'date'       => '2012-08-09',
                                                      'periods'    => array('day', 'week', 'month', 'year'),
                                                      'segment'    => 'browserCode==EP',
                                                      'testSuffix' => '_nonPreArchivedSegment'));
        
        return $results;
    }

    /**
     * @group        Integration
     * @group        ImportLogs
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
    
    public function getArchivePhpCronOptionsToTest()
    {
        return array(
            array('noOptions', array()),
            array('forceAllWebsites', array('--force-all-websites' => false)),
            array('forceAllPeriods_lastDay', array('--force-all-periods=86400')),
            array('forceAllPeriods_allTime', array('--force-all-periods')),
        );
    }
    
    /**
     Archive.php tests:
In all tests, test:
 - segments
 - etc.
     * @dataProvider getArchivePhpCronOptionsToTest
     * @group        Integration
     * @group        ImportLogs
     */
    public function testArchivePhpCron($optionGroupName, $archivePhpOptions)
    {
        self::deleteArchiveTables();
        $this->runArchivePhpCron($archivePhpOptions);
        Piwik_ArchiveProcessing::$forceDisableArchiving = true;
        
        foreach ($this->getApiForCronTest() as $testInfo) {
            list($api, $params) = $testInfo;
            $params['testSuffix'] = '_' . $optionGroupName;
            
            $this->runApiTests($api, $params);
        }
    }

    public function getOutputPrefix()
    {
        return 'ImportLogs';
    }
    
    private function runArchivePhpCron($options)
    {
        $archivePhpScript = PIWIK_INCLUDE_PATH . '/tests/PHPUnit/proxy/archive.php';
        $urlToProxy = Test_Piwik_BaseFixture::getRootUrl() . 'tests/PHPUnit/proxy/';
        
        $segments = addslashes(Piwik_Common::json_encode($this->getSegmentsToPreArchive()));
        
        // create the command
        $cmd = "php \"$archivePhpScript\" --url=\"$urlToProxy\" --segments=\"$segments\" ";
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

