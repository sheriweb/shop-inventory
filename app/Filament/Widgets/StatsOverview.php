<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\Category;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';
    protected function getStats(): array
    {
        $totalProducts = Product::count();
        $totalCategories = Category::count();
        $totalSales = Sale::count();
        $totalRevenue = Sale::sum('total_amount');
        
        $lowStockProducts = Product::query()
            ->whereRaw('quantity_in_gaz <= min_stock_level')
            ->orWhereRaw('quantity_in_meter <= min_stock_level')
            ->count();

        return [
            Stat::make('Total Products', $totalProducts)
                ->description('All products in inventory')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),
                
            Stat::make('Categories', $totalCategories)
                ->description('Product categories')
                ->descriptionIcon('heroicon-m-tag')
                ->color('success'),
                
            Stat::make('Total Sales', $totalSales)
                ->description('All-time sales')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('warning'),
                
            Stat::make('Total Revenue', 'Rs. ' . number_format($totalRevenue, 2))
                ->description('All-time revenue')
                ->descriptionIcon('heroicon-m-currency-rupee')
                ->color('success'),
                
            Stat::make('Low Stock Items', $lowStockProducts)
                ->description('Products below minimum stock level')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockProducts > 0 ? 'danger' : 'success'),
        ];
    }
}
