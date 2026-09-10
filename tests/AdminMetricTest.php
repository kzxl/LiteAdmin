<?php

declare(strict_types=1);

namespace LiteAdmin\Tests;

use LiteAdmin\AdminDashboard;
use LiteAdmin\Metric\AdminMetric;
use LiteORM\EntityManager;
use Nyholm\Psr7\{Response, ServerRequest};
use PHPUnit\Framework\TestCase;

final class AdminMetricTest extends TestCase
{
    public function testMetricCreationAndRendering(): void
    {
        $metric = AdminMetric::make('Monthly Revenue', 125000)
            ->prefix('$')
            ->suffix(' USD')
            ->icon('💰')
            ->description('+15% from last month');

        $this->assertEquals('Monthly Revenue', $metric->label);
        $this->assertEquals(125000, $metric->value);
        $this->assertEquals('$', $metric->prefix);
        $this->assertEquals(' USD', $metric->suffix);
        $this->assertEquals('💰', $metric->icon);
        $this->assertEquals('+15% from last month', $metric->description);

        $html = $metric->render();
        $this->assertStringContainsString('metric-card', $html);
        $this->assertStringContainsString('Monthly Revenue', $html);
        $this->assertStringContainsString('$125,000 USD', $html);
        $this->assertStringContainsString('💰', $html);
        $this->assertStringContainsString('+15% from last month', $html);
    }

    public function testDashboardRendersMetricsGrid(): void
    {
        $em = new EntityManager('sqlite::memory:');
        $admin = new AdminDashboard($em, null, '/admin');

        $m1 = AdminMetric::make('New Customers', 450)->icon('👥');
        $m2 = AdminMetric::make('Pending Orders', 12)->icon('📦');

        $admin->addMetric($m1)->addMetric($m2);

        $this->assertCount(2, $admin->getMetrics());

        $request = new ServerRequest('GET', '/admin');
        $response = $admin->handleIndex($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();

        $this->assertStringContainsString('metrics-grid', $body);
        $this->assertStringContainsString('New Customers', $body);
        $this->assertStringContainsString('450', $body);
        $this->assertStringContainsString('Pending Orders', $body);
        $this->assertStringContainsString('12', $body);
    }
}
