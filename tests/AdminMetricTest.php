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
        $metric = AdminMetric::make('Doanh thu tháng', 125000000)
            ->prefix('₫')
            ->suffix(' VNĐ')
            ->icon('💰')
            ->description('+15% so với tháng trước');

        $this->assertEquals('Doanh thu tháng', $metric->label);
        $this->assertEquals(125000000, $metric->value);
        $this->assertEquals('₫', $metric->prefix);
        $this->assertEquals(' VNĐ', $metric->suffix);
        $this->assertEquals('💰', $metric->icon);
        $this->assertEquals('+15% so với tháng trước', $metric->description);

        $html = $metric->render();
        $this->assertStringContainsString('metric-card', $html);
        $this->assertStringContainsString('Doanh thu tháng', $html);
        $this->assertStringContainsString('₫125,000,000 VNĐ', $html);
        $this->assertStringContainsString('💰', $html);
        $this->assertStringContainsString('+15% so với tháng trước', $html);
    }

    public function testDashboardRendersMetricsGrid(): void
    {
        $em = new EntityManager('sqlite::memory:');
        $admin = new AdminDashboard($em, null, '/admin');

        $m1 = AdminMetric::make('Khách hàng mới', 450)->icon('👥');
        $m2 = AdminMetric::make('Đơn hàng chờ xử lý', 12)->icon('📦');

        $admin->addMetric($m1)->addMetric($m2);

        $this->assertCount(2, $admin->getMetrics());

        $request = new ServerRequest('GET', '/admin');
        $response = $admin->handleIndex($request, new Response());

        $this->assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();

        $this->assertStringContainsString('metrics-grid', $body);
        $this->assertStringContainsString('Khách hàng mới', $body);
        $this->assertStringContainsString('450', $body);
        $this->assertStringContainsString('Đơn hàng chờ xử lý', $body);
        $this->assertStringContainsString('12', $body);
    }
}
