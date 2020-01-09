<?php

namespace Icamys\SitemapGenerator;

use BadMethodCallException;
use DateTime;
use InvalidArgumentException;
use LengthException;
use OutOfRangeException;
use phpmock\phpunit\PHPMock;
use phpmock\spy\Spy;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class SitemapGeneratorTest extends TestCase
{
    use PHPMock;

    private $testDomain = 'http://example.com';

    /**
     * @var SitemapGenerator
     */
    private $g;

    /**
     * @var FileSystem
     */
    private $fs;

    /**
     * @var DateTime current datetime
     */
    private $now;

    public function getSizeDiffInPercentsProvider()
    {
        return [
            ['args' => [100, 90], 'expected' => -10],
            ['args' => [100, 110], 'expected' => 10],
            ['args' => [200, 100], 'expected' => -50],
        ];
    }

    /**
     * @dataProvider getSizeDiffInPercentsProvider
     * @throws ReflectionException
     */
    public function testGetSizeDiffInPercents($args, $expected)
    {
        $actual = $this->invokeMethod($this->g, 'getDiffInPercents', $args);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Call protected/private method of a class.
     * @param object &$object Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method.
     * @return mixed Method return.
     * @throws ReflectionException
     */
    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function testSetSitemapFilenameException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->g->setSitemapFilename('');
    }

    public function testSetSitemapIndexFilenameException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->g->setSitemapIndexFilename('');
    }

    public function testSetRobotsFileNameException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->g->setRobotsFileName('');
    }

    public function testSetRobotsFileName()
    {
        $return = $this->g->setRobotsFileName('robots.txt');
        $this->assertEquals($this->g, $return);
    }

    public function testSetMaxURLsPerSitemapLeftOutOfRangeException()
    {
        $this->expectException(OutOfRangeException::class);
        $this->g->setMaxURLsPerSitemap(0);
    }

    public function testSetMaxURLsPerSitemapRightOutOfRangeException()
    {
        $this->expectException(OutOfRangeException::class);
        $this->g->setMaxURLsPerSitemap(50001);
    }

    public function testAddURL()
    {
        $nowStr = $this->now->format('Y-m-d\TH:i:sP');
        for ($i = 0; $i < 2; $i++) {
            $this->g->addURL('/product-'.$i . '/', $this->now, 'always', '0.8' );
        }
        $urlArray = $this->g->getURLsArray();

        $this->assertCount(2, $urlArray);
        $this->assertEquals('/product-0/', $urlArray[0][$this->g::ATTR_NAME_LOC]);
        $this->assertEquals($nowStr, $urlArray[0][$this->g::ATTR_NAME_LASTMOD]);
        $this->assertEquals('always', $urlArray[0][$this->g::ATTR_NAME_CHANGEFREQ]);
        $this->assertEquals('0.8', $urlArray[0][$this->g::ATTR_NAME_PRIORITY]);
        $this->assertEquals('/product-1/', $urlArray[1][$this->g::ATTR_NAME_LOC]);
    }

    public function testAddURLWithAlternates()
    {
        $alternates = [
            ['hreflang' => 'de', 'href' => "http://www.example.com/de"],
            ['hreflang' => 'fr', 'href' => "http://www.example.com/fr"],
        ];
        $nowStr = $this->now->format('Y-m-d\TH:i:sP');
        $this->g->addURL('/product-0/', $this->now, 'always', '0.8' , $alternates);
        $urlArray = $this->g->getURLsArray();
        $this->assertCount(1, $urlArray);
        $this->assertEquals('/product-0/', $urlArray[0][$this->g::ATTR_NAME_LOC]);
        $this->assertEquals($nowStr, $urlArray[0][$this->g::ATTR_NAME_LASTMOD]);
        $this->assertEquals('always', $urlArray[0][$this->g::ATTR_NAME_CHANGEFREQ]);
        $this->assertEquals('0.8', $urlArray[0][$this->g::ATTR_NAME_PRIORITY]);
        $this->assertCount(2, $urlArray[0][$this->g::ATTR_NAME_ALTERNATES]);
        $this->assertCount(2, $urlArray[0][$this->g::ATTR_NAME_ALTERNATES][0]);
        $this->assertEquals('de', $urlArray[0][$this->g::ATTR_NAME_ALTERNATES][0]['hreflang']);
        $this->assertEquals('http://www.example.com/de', $urlArray[0][$this->g::ATTR_NAME_ALTERNATES][0]['href']);
        $this->assertEquals('fr', $urlArray[0][$this->g::ATTR_NAME_ALTERNATES][1]['hreflang']);
        $this->assertEquals('http://www.example.com/fr', $urlArray[0][$this->g::ATTR_NAME_ALTERNATES][1]['href']);
    }

    public function testAddURLInvalidLocException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->g->addURL('', $this->now, 'always', '0.8' );
    }

    public function testAddURLTooLongException()
    {
        $this->expectException(InvalidArgumentException::class);
        $url = str_repeat("s", 5000);
        $this->g->addURL($url, $this->now, 'always', '0.8' );
    }

    public function testWriteSitemapBadMethodCallException()
    {
        $this->expectException(BadMethodCallException::class);
        $this->g->writeSitemap();
    }

    public function testWriteSitemapWithManySitemaps()
    {
        $this->fs->expects($this->exactly(3))
            ->method('file_put_contents')
            ->withConsecutive(
                [$this->equalTo('sitemap-index.xml'), $this->stringStartsWith('<?xml ')],
                [$this->equalTo('sitemap1.xml'), $this->stringStartsWith('<?xml ')],
                [$this->equalTo('sitemap2.xml'), $this->stringStartsWith('<?xml ')]
            );

        $this->g->setMaxURLsPerSitemap(1);
        $this->g->setSitemapFilename("sitemap.xml");
        $this->g->setSitemapIndexFilename("sitemap-index.xml");
        $this->g->addURL('/product-1/', $this->now, 'always', '0.8');
        $this->g->addURL('/product-2/', $this->now, 'always', '0.8');
        $this->g->createSitemap();
        $this->g->writeSitemap();
    }

    public function testWriteSitemapWithManySitemapsAndGzipEnabled()
    {
        $this->fs->expects($this->exactly(1))
            ->method('file_put_contents')
            ->withConsecutive(
                [$this->equalTo('sitemap-index.xml'), $this->stringStartsWith('<?xml ')]
            );

        $fileDescriptorMock = true;

        $this->fs->expects($this->exactly(3))
            ->method('gzopen')
            ->withConsecutive(
                [$this->equalTo('sitemap-index.xml.gz'), $this->equalTo('w')],
                [$this->equalTo('sitemap1.xml.gz'), $this->stringStartsWith('w')],
                [$this->equalTo('sitemap2.xml.gz'), $this->stringStartsWith('w')]
            )
            ->willReturn($fileDescriptorMock)
        ;

        $this->fs->expects($this->exactly(3))
            ->method('gzwrite')
            ->withConsecutive(
                [$this->equalTo($fileDescriptorMock), $this->stringStartsWith('<?xml ')],
                [$this->equalTo($fileDescriptorMock), $this->stringStartsWith('<?xml ')],
                [$this->equalTo($fileDescriptorMock), $this->stringStartsWith('<?xml ')]
            );

        $this->fs->expects($this->exactly(3))
            ->method('gzclose')
            ->withConsecutive(
                [$this->equalTo($fileDescriptorMock)],
                [$this->equalTo($fileDescriptorMock)],
                [$this->equalTo($fileDescriptorMock)]
            );

        $this->g->setMaxURLsPerSitemap(1);
        $this->g->setSitemapFilename("sitemap.xml");
        $this->g->setSitemapIndexFilename("sitemap-index.xml");
        $this->g->toggleGZipFileCreation();
        $this->g->addURL('/product-1/', $this->now, 'always', '0.8');
        $this->g->addURL('/product-2/', $this->now, 'always', '0.8');
        $this->g->createSitemap();
        $this->g->writeSitemap();
    }

    public function testWriteSitemapWithSingleSitemap()
    {
        $this->fs->expects($this->exactly(1))
            ->method('file_put_contents')
            ->withConsecutive(
                [$this->equalTo('sitemap.xml'), $this->stringStartsWith('<?xml ')]
            );

        $this->g->setMaxURLsPerSitemap(1);
        $this->g->setSitemapFilename("sitemap.xml");
        $this->g->setSitemapIndexFilename("sitemap-index.xml");
        $this->g->addURL('/product-1/', $this->now, 'always', '0.8' );
        $this->g->createSitemap();
        $this->g->writeSitemap();
    }

    public function testWriteFileException()
    {
        $this->fs->expects($this->exactly(1))
            ->method('file_put_contents')
            ->withConsecutive(
                [$this->equalTo('sitemap.xml'), $this->stringStartsWith('<?xml ')]
            )
            ->willReturn(false)
        ;
        $this->expectException(RuntimeException::class);

        $this->g->setMaxURLsPerSitemap(1);
        $this->g->setSitemapFilename("sitemap.xml");
        $this->g->setSitemapIndexFilename("sitemap-index.xml");
        $this->g->addURL('/product-1/', $this->now, 'always', '0.8' );
        $this->g->createSitemap();
        $this->g->writeSitemap();
    }

    public function testOpenGzipFileException()
    {
        $this->fs->expects($this->exactly(1))
            ->method('file_put_contents')
            ->withConsecutive(
                [$this->equalTo('sitemap.xml'), $this->stringStartsWith('<?xml ')]
            );

        $fileDescriptorMock = false;

        $this->fs->expects($this->exactly(1))
            ->method('gzopen')
            ->withConsecutive(
                [$this->equalTo('sitemap.xml.gz'), $this->equalTo('w')]
            )
            ->willReturn($fileDescriptorMock)
        ;

        $this->expectException(RuntimeException::class);

        $this->g->toggleGZipFileCreation();
        $this->g->addURL('/product-1/', $this->now, 'always', '0.8');
        $this->g->createSitemap();
        $this->g->writeSitemap();
    }

    public function testWriteGzipFileException()
    {
        $this->fs->expects($this->exactly(1))
            ->method('file_put_contents')
            ->withConsecutive(
                [$this->equalTo('sitemap.xml'), $this->stringStartsWith('<?xml ')]
            );

        $fileDescriptorMock = true;

        $this->fs->expects($this->exactly(1))
            ->method('gzopen')
            ->withConsecutive(
                [$this->equalTo('sitemap.xml.gz'), $this->equalTo('w')]
            )
            ->willReturn($fileDescriptorMock)
        ;

        $this->fs->expects($this->exactly(1))
            ->method('gzwrite')
            ->withConsecutive(
                [$this->equalTo($fileDescriptorMock), $this->stringStartsWith('<?xml ')]
            )
            ->willReturn(0)
        ;

        $this->expectException(RuntimeException::class);

        $this->g->toggleGZipFileCreation();
        $this->g->addURL('/product-1/', $this->now, 'always', '0.8');
        $this->g->createSitemap();
        $this->g->writeSitemap();
    }

    public function testCloseGzipFileException()
    {
        $this->fs->expects($this->exactly(1))
            ->method('file_put_contents')
            ->withConsecutive(
                [$this->equalTo('sitemap.xml'), $this->stringStartsWith('<?xml ')]
            );

        $fileDescriptorMock = true;

        $this->fs->expects($this->exactly(1))
            ->method('gzopen')
            ->withConsecutive(
                [$this->equalTo('sitemap.xml.gz'), $this->equalTo('w')]
            )
            ->willReturn($fileDescriptorMock)
        ;

        $this->fs->expects($this->exactly(1))
            ->method('gzwrite')
            ->withConsecutive(
                [$this->equalTo($fileDescriptorMock), $this->stringStartsWith('<?xml ')]
            )
        ;

        $this->fs->expects($this->exactly(1))
            ->method('gzclose')
            ->willReturn(false)
        ;

        $this->expectException(RuntimeException::class);

        $this->g->toggleGZipFileCreation();
        $this->g->addURL('/product-1/', $this->now, 'always', '0.8');
        $this->g->createSitemap();
        $this->g->writeSitemap();
    }

    public function testWriteSitemapWithSingleSitemapAndGzipEnabled()
    {
        $this->fs->expects($this->exactly(1))
            ->method('file_put_contents')
            ->withConsecutive(
                [$this->equalTo('sitemap.xml'), $this->stringStartsWith('<?xml ')]
            );

        $this->fs->expects($this->exactly(1))->method('gzopen');
        $this->fs->expects($this->exactly(1))->method('gzwrite');
        $this->fs->expects($this->exactly(1))->method('gzclose');

        $this->g->setMaxURLsPerSitemap(1);
        $this->g->setSitemapFilename("sitemap.xml");
        $this->g->setSitemapIndexFilename("sitemap-index.xml");
        $this->g->toggleGZipFileCreation();
        $this->g->addURL('/product-1/', $this->now, 'always', '0.8' );
        $this->g->createSitemap();
        $this->g->writeSitemap();
    }

    public function testWriteSitemapWithSingleSitemapWithAlternates()
    {
        $this->fs->expects($this->exactly(1))
            ->method('file_put_contents')
            ->withConsecutive(
                [$this->equalTo('sitemap.xml'), $this->stringStartsWith('<?xml ')]
            );

        $alternates = [
            ['hreflang' => 'de', 'href' => $this->testDomain . "/de"],
            ['hreflang' => 'fr', 'href' => $this->testDomain . "/fr"],
        ];
        $this->g->setMaxURLsPerSitemap(1);
        $this->g->setSitemapFilename("sitemap.xml");
        $this->g->setSitemapIndexFilename("sitemap-index.xml");
        $this->g->addURL('/product-1/', $this->now, 'always', '0.8', $alternates);
        $this->g->createSitemap();
        $this->g->writeSitemap();
    }

    public function testCreateSitemapWithDefaultSitemap()
    {
        $this->g = new SitemapGenerator($this->testDomain, '', null);
        $this->assertTrue(true);
    }

    public function testCreateTooLargeSitemap()
    {
        $this->expectException(LengthException::class);
        $this->g->setSitemapFilename("sitemap.xml");
        $this->g->setSitemapIndexFilename("sitemap-index.xml");
        $longLine = str_repeat('c', 2040);
        for ($i = 0; $i < 25000; $i++) {
            $this->g->addURL($longLine . $i, $this->now, 'always', '0.8');
        }
        $this->g->createSitemap();
    }

    public function testCreateExactMaxSitemaps()
    {
        $this->g->setMaxURLsPerSitemap(1);
        $this->g->setSitemapFilename("sitemap.xml");
        $this->g->setSitemapIndexFilename("sitemap-index.xml");
        for ($i = 0; $i < 50000; $i++) {
            $this->g->addURL($i, $this->now, 'always', '0.8');
        }
        $this->g->createSitemap();
        $this->assertTrue(true);
    }

    public function testCreateTooManySitemaps()
    {
        $this->expectException(LengthException::class);
        $this->g->setMaxURLsPerSitemap(1);
        $this->g->setSitemapFilename("sitemap.xml");
        $this->g->setSitemapIndexFilename("sitemap-index.xml");
        for ($i = 0; $i < 50001; $i++) {
            $this->g->addURL($i, $this->now, 'always', '0.8');
        }
        $this->g->createSitemap();
    }

    public function testAddTooLargeUrl()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->g->addURL(str_repeat('c', 5000), $this->now, 'always', '0.8');
    }

    public function testCreateSitemapExceptionWhenNoUrlsAdded() {
        $this->expectException(BadMethodCallException::class);
        $this->g->createSitemap();
    }

    public function testCreateGeneratorWithBasepathWithoutTrailingSlash() {
        $this->fs->expects($this->exactly(1))
            ->method('file_put_contents')
            ->withConsecutive(
                [$this->equalTo('path/sitemap.xml'), $this->stringStartsWith('<?xml ')]
            );

        $this->g = new SitemapGenerator($this->testDomain, 'path', $this->fs);
        $this->g->setMaxURLsPerSitemap(1);
        $this->g->setSitemapFilename("sitemap.xml");
        $this->g->setSitemapIndexFilename("sitemap-index.xml");
        $this->g->addURL('/product-1/', $this->now, 'always', '0.8' );
        $this->g->createSitemap();
        $this->g->writeSitemap();
    }

    public function testGetUrlsCount() {
        $this->g->setMaxURLsPerSitemap(10);
        $this->g->addURL('/product-1/', $this->now, 'always', '0.8' );
        $this->g->addURL('/product-2/', $this->now, 'always', '0.8' );
        $this->assertEquals(2, $this->g->getURLsCount());
    }

    protected function setUp(): void
    {
        $this->fs = $this->createMock(FileSystem::class);
        $this->g = new SitemapGenerator($this->testDomain, '', $this->fs);
        $this->now = new DateTime();
    }

    protected function tearDown(): void
    {
        unset($this->g);
    }
}
