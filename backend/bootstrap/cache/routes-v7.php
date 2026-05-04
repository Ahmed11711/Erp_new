<?php

/*
|--------------------------------------------------------------------------
| Load The Cached Routes
|--------------------------------------------------------------------------
|
| Here we will decode and unserialize the RouteCollection instance that
| holds all of the route information for an application. This allows
| us to instantaneously load the entire route map into the router.
|
*/

app('router')->setCompiledRoutes(
    array (
  'compiled' => 
  array (
    0 => false,
    1 => 
    array (
      '/laravel-websockets' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::0W9fHg7WQcfK6Cnm',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/laravel-websockets/auth' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::dvu2VMymvS8wT3WB',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/laravel-websockets/event' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::pLh481jvpU4G3gDk',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/laravel-websockets/statistics' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::i1BobBpuEskZCfqs',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/sanctum/csrf-cookie' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'sanctum.csrf-cookie',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/_ignition/health-check' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'ignition.healthCheck',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/_ignition/execute-solution' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'ignition.executeSolution',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/_ignition/update-config' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'ignition.updateConfig',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/broadcasting/auth' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::YClSb4J2YQlutGiA',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'POST' => 1,
            'HEAD' => 2,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/meta-test' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::fyfSNfIabnFbPkMr',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/test' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::3VLxbMOp1x1FZtPI',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/meta/webhook' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::k2eaz1X36kT6RyU0',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::5WsTAv2ZOnWO795L',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/meta/webhook-health' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Xr1dwJhRgyWEF5WP',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shopify/webhook' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::qsyBTI6LdtIsW4Cj',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/webhooks/shipping/update' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::o81Eca56pneEFyES',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/auth/login' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::RcaZovAzdKLjXAZC',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/auth/logout' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::J7G1pUbAd8Qh2aTK',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/auth/refresh' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::rBx7oZKTfQpKhmQG',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/auth/me' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::0aRXBGCOu2e4pdv0',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/webhook' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::s6tZNXmclDkxixpA',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/usersnotification' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::lPjE9AdbEu67EjGR',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/notification' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::1QEFR2WT5lSH3bEX',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::UhthlrWx2VQzZNoy',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/recievednotification' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Kx7wfP2ROYShlLOc',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/sentnotification' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::WzZELDr7kL4khxAi',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/send' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::HanSOerM66k2x8sb',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/send-template' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::p9Cp3YYLiRzH84fp',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/send-from-order' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::6AtZJENCOVeXZM86',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/meta-templates' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::qP9no81VGKOGWr8u',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/send-meta-template-from-order' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ioGR5goXfDx95xWT',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/customers/by-phone' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ouoCRQ1XIQy9cKei',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/customers/whatsapp-snippet' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::YJJN13nBKWPgZKpd',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/customers' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::nb09lQvcb6tNQAVl',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/templates' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Dfn2tv6HRXHgL4DN',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::LMeXTckqva7M18zJ',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/phone-numbers' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::W875CTnGHS2C6P5B',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/assignments' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::igKXAgSn1kPxOSvt',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/user-phone-numbers' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::VCgxkmg4UIu7lBzK',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/assign-users' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::C4zO7TPrUQX2t7Gl',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/whatsapp/remove-assignment' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ociwDgrKyRUxv1hH',
          ),
          1 => NULL,
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/order_source' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::U9H0SZzUbEOuscI9',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::bnBLo7ZNkCQDmIEY',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shipping_methods' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::m5T6g51WZ7aOd07n',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::0eFYCokO6xwPskyy',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shippinglines' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::klURRbi4CGFOyHPy',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::GqXwqs0hkhjPmdoQ',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/orders/search' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hby7mHF33XgwQQIT',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/productions' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hmntJYciWwNTuYsf',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::5MfUWa8zCNPHg3h9',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shippingcompanySelect' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::jrAkTd754bhmyaP1',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/bankSelect' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hIXd3ikcJ7OXY4Le',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/categories/cat_orders' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::MaA3bmbIqqt9yPzA',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/reports/categoriesSellReports' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hxpzSK4FyoFF3Eaj',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/reports/shipping-companies' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::IC4R9ABabICUPieD',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/transactions/by-supplier-order/search' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::jeXG0X0BIcQAs9GW',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/categories/warehouse_balance' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::mCW6t2MFaucwz6WD',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/categories/warehousedetails' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::xv6BhUi0rGMCHfBP',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/categories/categoryDetailsByWherehouse' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::H9zL7FtembeOT8R3',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/categoryquantity' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::94GSZAFa7wseD7gV',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/categories/monthlyinventory' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::rCJmrJwAAXRx80yC',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/categories/monthlyInventoryDetailsByWherehouse' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::UOvhxavWdKgmqDyI',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/categories/categoryByWarehouse' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Xf0ZWwNre5xxWCEz',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/suppliers/search' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::qNkNgkyej9gHMAup',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/suppliers/StoreSupplierType' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::WfkiNNZLBVC14iaC',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/suppliers/getAllSupplierTypes' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::VNn0Jq2nkcm6yX99',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/suppliers/supplier_names' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ueTRwcbmeqag5yLC',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/suppliers' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'suppliers.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'suppliers.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/inventory/import/items' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::UyHegoSfYW138hqs',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/inventory/import/opening-balances' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::6GauUWLd111sKASl',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/inventory/import/adjustments' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::m7Z3lrBVE2iXrQ2z',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/purchases/search' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::d13e98TDsxckOZxq',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/purchases' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'purchases.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'purchases.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/banks' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::OLc0JDaNudVBSvEH',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::R00DMY1Eauoco2Fq',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/FactoryBankMovements' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovements.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovements.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/FactoryBankMovementsDetails' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovementsDetails.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovementsDetails.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/FactoryBankMovementsCustody' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovementsCustody.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovementsCustody.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/incomelist' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'incomelist.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'incomelist.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/bank/transfermoney' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::kHefBGYObUL61C30',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/expense/search' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::a4hf7amjA2ClobRi',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/expense' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'expense.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'expense.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/expense_kind/search' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::oemgNb8LiZzLBizI',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/expense_kind' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'expense_kind.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'expense_kind.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/assets' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'assets.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'assets.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/cimmitments' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'cimmitments.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'cimmitments.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/covenants' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hDQl6Uu44wK8wepT',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::HXFrIRiZh5gbQvy1',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/incomes' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'incomes.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'incomes.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/categories/search' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::jiwXSXf2ZlYkfu8Y',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/measurements' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::5MjUuBOYEeJV1H6o',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ZspSULvpmEeFI0Ta',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/allcategories' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::A88r6LfTFXj9uhOo',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/categories' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::IZdtvfAXztNgA8m7',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::HJVgINU7GUWskhdo',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/getCategoryByStockId' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::RKY4ikE5vrlufpt6',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/recipes' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::tzMeIJQNXjl4VgiM',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::BXLz1RkFQ2S1IVbn',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/recipes/bulk-delete' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::IFiyaDnQZ8uNVGg0',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/recipes/import' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::NeAGK5shZ7owLNde',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/recipes/import/confirm' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::2m7ZVQWsiJ2xVLHW',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/recipes/import/cancel' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::S5dUmhuWorGOOCeM',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/allnotification' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::kCayXoeA4WqSOr7h',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/manufacture' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::cZkLCgOqpM8mGdYq',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Deir5QtSqSMqAC8S',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/manufacture/manfucture_by_warhouse' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hPQ1zB5Q16k0mUoS',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/manufacture/confirm' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::UlwiphKI0ZzxTOje',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/manufacture/confirmed' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::bQMKWsYnWsWqSBiR',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/manufacturing/production-orders' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::cnC9n8MsiTT9rEra',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::WnJNYjzohEyn7rOJ',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/pendingBanks' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::9qDTeI9HnFPBTjmt',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::NSFhxEaSPTFrW9TI',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/users' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::0eyrgiYwGf3c2IDX',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/register' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::5JFYDhI0nnC2uhJo',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/approvals' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'approvals.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'approvals.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/revieworder' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::12pIcB72ne4sNTNm',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/tracking' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::cJsK5v4Cs8faHzPK',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/tracking/undo' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::xdAN7JVwAMyYC6wt',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/getActions' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Shvv72ON3zS9NHra',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/stocks' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'stock.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'stock.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shopify/status' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::dVLLyzsGvS3aacEP',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shopify/sync-product-mappings' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::A8AADjgfVAwtdlEP',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shopify/sync-orders' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::UrC3hQF0IUxdVAtN',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shopify/product-mappings' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::QF7AcB9w8wBNN8xi',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shopify/integration/orders' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ZWWFnd1LN4NlsZf8',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shopify/integration/products' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::BSnJr86zg70KigOz',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shopify/integration/failed-jobs' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::AHNrkqOl3FjpRWKI',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/collectorder-bulk' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::WZ2EjIH5v6dys5qT',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/employees/search' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::3LRTZTAnQk2ikXXp',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/employeespermonth' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ffC4Nnwcyi9LmXu4',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/getEmpDataPerMonth' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::77IIzH2hBXktzTSN',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/empHoursPermission' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::vaKxzEHcOk91AmEY',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/empHoursPermissionall' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::O1xrPkLZUH6LWZ8L',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/employees/absences' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::pQpWxHdlq7XcsM5P',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/employee/absencestatus' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Km635EJqd3YRRa4G',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/employees/accountstatment' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::c4C7DYJ6Tq2iGFbC',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/employees/excelfingerprintdata' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::wkeNYYGathsCG5CK',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/updatefingerprintsheet' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::rijZLww2HW80s8cG',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/reviewMonth' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::J5sWCZjxv0CrwnZv',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/absencededuction' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::1tnsYKIktgruZciZ',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/addFixedChangedSalary' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::CRrWdjlr3TTG0Wq6',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/employees' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employees.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'employees.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/employeemerit' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employeemerit.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'employeemerit.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/employeesubtraction' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employeesubtraction.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'employeesubtraction.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/employeeadvancepayment' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employeeadvancepayment.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'employeeadvancepayment.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/employeemonthpaid/bulk' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::EQN711zqAsmL303M',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/employeemonthpaid' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employeemonthpaid.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'employeemonthpaid.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/edit-lead' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hxLpBLqv8jDLDmnz',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/lead/team-users' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::xsIZrKImlYi5NMK5',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/lead/activity-stats' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::GKKNVmrjaod60xV1',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/lead/recommenders/pending' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::frdsABCqu0ymcvyF',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/lead' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'lead.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'lead.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/lead-source' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'lead-source.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'lead-source.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/lead-tool' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'lead-tool.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'lead-tool.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/lead-industry' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'lead-industry.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'lead-industry.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/lead-statuses' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'lead-statuses.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'lead-statuses.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/orders' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::JnDmyyJ56pgLNbns',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/phonenumbers' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::odSrOrFOkeUBfXHV',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/companies' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::IgVt48OavAFxzdMw',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::oJD6HFyFq6rlVHeh',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/getOrdersNumbers' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::6oyxmwIk1HiHMtCH',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/ShippingLineStatement' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'ShippingLineStatement.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'ShippingLineStatement.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/companies/search' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::2VDurLip6ht7kmOk',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shippingcompany/search' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::HqzqeAgZw8xCZNXz',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/shippingcompanies' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::pEmLGJcl1czzHFzx',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::MTWH75nWjuOiIQXR',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/offer' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'offer.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'offer.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/userrevieworder' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::SvPT0Fm9duqZ6uT7',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/transactions/by-customer-order/search' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::SqAVT4R3VdooDLie',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/transactions/by-customer-order/detailed' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::6x3STkW7kXwmXTJ3',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/tree_accounts/bulk-balance-adjustment' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ZG9XlFH2Y690yZTG',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/tree_accounts' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'tree_account.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'tree_account.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/vouchers' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::OgkgNd3ktsqFC15K',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::WHXAqzUc6L2mtOec',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/cost-centers' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::dflMef0e5dhAfPXv',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::WBOzKaUK8oupEepj',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/cost-centers/tree' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::VzAOwKtRDH9cx8dj',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/safes' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::AMsICZZ3HIQ32EWP',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::mt4RvrfKEnboUO2H',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/safes/transfer' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::XXhQvTPEtG9wAEpz',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/safes/direct-transaction' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::AlN8nY5SobUcSLAx',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/daily-entries' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ar2dVzQv0tCh39DX',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::LtPh2dNawRBymG1y',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/banks' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::lhQ6OOr6o39VQv0k',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::L6MR7rJFLiS89ckw',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/banks/transfer' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::8hSYCLiqGMIz5NF0',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/banks/direct-transaction' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::aUnovgtXWSaKWVW2',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/capitals' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::xpKZlYfkvF7fk8Vf',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hoPOm1EE8HrG7IDX',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/payment-sources' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::zNMr8sCmSaasGBLT',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/reports/daily-ledger' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::lo9ii8d965vAIbqy',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/reports/account-balance' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::5JUoYMB0znFVSXrn',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/reports/trial-balance' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::AWw0EK4Y6W5GTjmL',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/reports/accounting-tree' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::cV7wBdQcueUc1LIg',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/reports/account-statement' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::co15p88lBaHMA4G5',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/reports/account-hierarchy' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::jCXhU4cwssjQJ92V',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/reports/validate-income-structure' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::7xzcSALzp8nJ4BAh',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/reports/income-statement' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::6oyIrG0blw8E0bny',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/reports/product-performance' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::FrIq6nIaQ8Lmhccn',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/reports/category-profitability' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::va0IaQ6RZtoNRfNW',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/process-cash-transaction' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::qjnR0Pq6ekKe4ikm',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/update-hierarchy-balances' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::uJecO9AXjuU8nZps',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/recalculate-all-hierarchy-balances' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::aTymc10ixqcJxpMW',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/service-accounts' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::u7RlMOOPc0B08ZI7',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::TfmCSvqFyY0RLNTK',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/service-accounts/transfer' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::q1HYB0V5rgJ7rfJf',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/settings' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::XZvDjlGhPUr2PzpU',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::IPLqYSJrwicnKYmJ',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/accounting/settings/update-existing' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::YddSCpCozPtG8fXv',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/report/order' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::2hcjiLtPhuy3ZC0L',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/report/getByOrderId' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::AiWAt5USlqXqf9JB',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/images/whatsapp-meta-default.png' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::eEDIkItVuCpBAdGL',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
    ),
    2 => 
    array (
      0 => '{^(?|/laravel\\-websockets/api/([^/]++)/statistics(*:51)|/api/(?|notification/(?|([^/]++)(?|(*:93))|delete/([^/]++)(*:116))|c(?|o(?|n(?|versations/([0-9]+)/messages(*:165)|firm/([^/]++)(*:186))|llectorder/([^/]++)(*:214)|mpanies/(?|([^/]++)(*:241)|companycollect/([^/]++)(*:272)))|ategor(?|ies/(?|categories_details/([^/]++)(*:325)|([^/]++)(?|/(?|quantity(*:356)|revision\\-(?|roll\\-forward(*:390)|lineage(*:405)))|(*:415)))|y/([^/]++)(*:435))|immitments/([^/]++)(?|/pay(*:470)|(*:478))|hange(?|CheckIn/([^/]++)(*:511)|status/([^/]++)(*:534)))|m(?|e(?|dia/([0-9]+)(*:564)|asurements/([^/]++)(?|(*:594)))|anufactur(?|e/done/([^/]++)(*:631)|ing/production\\-orders/(?|([0-9]+)(*:673)|([0-9]+)/start(*:695)|([0-9]+)/complete(*:720)|([0-9]+)/cancel(*:743))))|whatsapp/chat/([^/]++)(*:776)|o(?|rder(?|s/([^/]++)(*:805)|/(?|received/([^/]++)(*:834)|maintained/([^/]++)(*:861)))|ffer/([^/]++)(?|(*:887)))|s(?|uppliers/(?|deleteType/([^/]++)(*:932)|supplier(?|Pay/([^/]++)(*:963)|Details/([^/]++)(*:987))|([^/]++)(?|(*:1007)))|h(?|ip(?|ping(?|lines/([^/]++)(?|(*:1051))|compan(?|ies/([^/]++)(?|(*:1085))|y/([^/]++)(*:1105)))|order/([^/]++)(*:1130))|o(?|pify/integration/products/([^/]++)(*:1178)|rtage/([^/]++)(*:1201)))|tocks/([^/]++)(?|(*:1229)))|p(?|urchases/([^/]++)(?|(*:1264)|(*:1273))|roductions/([^/]++)(?|(*:1305))|artcollectorder/([^/]++)(*:1339))|FactoryBankMovements(?|/([^/]++)(?|(*:1384))|Details/([^/]++)(?|(*:1413))|Custody/([^/]++)(?|(*:1442)))|income(?|list/([^/]++)(?|(*:1478))|s/([^/]++)(?|(*:1501)))|banks/(?|([^/]++)(*:1529)|depositbank/([^/]++)(*:1558)|editBankBalance/([^/]++)(*:1591)|withDrawBank/([^/]++)(*:1621))|e(?|dit(?|expense/([^/]++)(*:1657)|category/([^/]++)(*:1683)|CheckInOrOut/([^/]++)(*:1713)|order/([^/]++)(*:1736))|xpense(?|/([^/]++)(?|(*:1767))|_kind/([^/]++)(?|(*:1794)))|mployee(?|permonth/([^/]++)(*:1832)|s(?|/(?|edit/([^/]++)(*:1862)|accountstatment/reviewed/([^/]++)(*:1904)|([^/]++)(?|(*:1924)))|ubtraction/([^/]++)(?|(*:1957)))|m(?|erit/([^/]++)(?|(*:1988))|onthpaid/([^/]++)(?|(*:2018)))|advancepayment/([^/]++)(?|(*:2055))))|delete(?|expense/([^/]++)(*:2092)|category/([^/]++)(*:2118))|a(?|ssets/(?|([^/]++)(?|(*:2152))|run\\-depreciation(*:2179))|pprovals/([^/]++)(?|(*:2209))|dd(?|CheckOut/([^/]++)(*:2241)|shippmentnumber/([^/]++)(*:2274)|note/([^/]++)(*:2296))|ccounting/(?|vouchers/([^/]++)(?|(*:2339))|cost\\-centers/([^/]++)(?|(*:2374))|s(?|afes/([^/]++)(?|(*:2404))|ervice\\-accounts/([^/]++)(*:2439))|daily\\-entries/([^/]++)(?|(*:2475))|banks/([^/]++)(?|(*:2502))))|re(?|cipes/(?|([0-9]+)(?|(*:2539))|([0-9]+)/check\\-stock(*:2570)|([0-9]+)/execute(*:2595)|([0-9]+)/movements(*:2622)|([0-9]+)/extra\\-costs(?|(*:2655))|([0-9]+)/extra\\-costs/([0-9]+)(?|(*:2698))|([0-9]+)/breakdown(*:2726))|adtempreview/([^/]++)(*:2757)|fuseorder/([^/]++)(*:2784))|user/delete/([^/]++)(*:2814)|g(?|etEmpDataPerMonth/([^/]++)(*:2853)|ooglesheet/([^/]++)(*:2881))|lead(?|/(?|recommenders/([^/]++)(?|(*:2926)|/toggle\\-done(*:2948))|([^/]++)(?|(*:2969)))|\\-(?|s(?|ource/([^/]++)(?|(*:3006))|tatuses/(?|([^/]++)(?|(*:3038))|active(*:3054)|closed\\-(?|won(*:3077)|lost(*:3090))|follow\\-up\\-leads(*:3117)|lead/([^/]++)(*:3139)))|tool/([^/]++)(?|(*:3166))|industry/([^/]++)(?|(*:3196))))|ShippingLineStatement/([^/]++)(?|(*:3241))|vip/([^/]++)(*:3263)|t(?|empreview/([^/]++)(*:3294)|ree_accounts/([^/]++)(?|/balance\\-adjustment(*:3347)|(*:3356)))))/?$}sDu',
    ),
    3 => 
    array (
      51 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::7cAi72TtXmfm0hFA',
          ),
          1 => 
          array (
            0 => 'appId',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      93 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::GXG9HnwINcDQxxtv',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::xcOtDF9jvteBhLPe',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      116 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Xbqy2JuRzjJ0Frhf',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      165 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ECsosy8zAGH4WhrW',
          ),
          1 => 
          array (
            0 => 'conversation_id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      186 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::sUMcYB36KD1A186s',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      214 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ABGgybFyySao55JM',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      241 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::MC28ycnP1oqzIfiK',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      272 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::XiPVBf108p5FS3J9',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      325 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Q7UGZOfNLP4M7zGI',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      356 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::t5PFVmAL7jx0PzHn',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PATCH' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      390 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::u0RGVqQViUXD5P5q',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      405 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::It4XsnHVwI5yJuab',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      415 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::TWOgmHDC6ppfEw4n',
          ),
          1 => 
          array (
            0 => 'category',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::JuYcOH6syvRKSSbt',
          ),
          1 => 
          array (
            0 => 'category',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'generated::iyupLZQxn7aW6WXE',
          ),
          1 => 
          array (
            0 => 'category',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      435 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::woQSrNniptk8gBIK',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      470 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::eooL5D4CwGbr7zrp',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      478 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'cimmitments.show',
          ),
          1 => 
          array (
            0 => 'cimmitment',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'cimmitments.update',
          ),
          1 => 
          array (
            0 => 'cimmitment',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'cimmitments.destroy',
          ),
          1 => 
          array (
            0 => 'cimmitment',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      511 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hm87xhuaxrHDboYN',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      534 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::IZ9kMjselTFzgh4D',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      564 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::JPiobp8oBCCReo6q',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      594 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::vNqAXru4PS98BEDj',
          ),
          1 => 
          array (
            0 => 'measurement',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::MJ2y2pAUZqfozNc5',
          ),
          1 => 
          array (
            0 => 'measurement',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'generated::I8J4RZ67JW5ZKTnK',
          ),
          1 => 
          array (
            0 => 'measurement',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      631 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::qdWkbXXOpHLAEhu1',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      673 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::x7ceK9oDVJ7k0uzl',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      695 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Gmjeh4cDoNUpPYgi',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      720 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Km7M9lHxk1xy2EpH',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      743 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::RUuBqLNTm4THMCzD',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      776 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ybrqMCnN1ZIZAjYG',
          ),
          1 => 
          array (
            0 => 'customerId',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      805 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::b5MaLQWwp1dOhggT',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      834 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::lhEm9lM97qx4rBjj',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      861 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::kKqNRNtxEci5S3Si',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      887 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'offer.show',
          ),
          1 => 
          array (
            0 => 'offer',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'offer.update',
          ),
          1 => 
          array (
            0 => 'offer',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'offer.destroy',
          ),
          1 => 
          array (
            0 => 'offer',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      932 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::wxUVfyNJHbefyiga',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      963 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::eSwf25lCLkc7Ffkk',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      987 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::L1osqre55P0uoHdb',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1007 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'suppliers.show',
          ),
          1 => 
          array (
            0 => 'supplier',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'suppliers.update',
          ),
          1 => 
          array (
            0 => 'supplier',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'suppliers.destroy',
          ),
          1 => 
          array (
            0 => 'supplier',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1051 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::VWHduG1zcMn3fOfL',
          ),
          1 => 
          array (
            0 => 'shippingline',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::n1cOrpBpcbvhgHNq',
          ),
          1 => 
          array (
            0 => 'shippingline',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'generated::sf1EeP8vIGSGu9gH',
          ),
          1 => 
          array (
            0 => 'shippingline',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1085 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::1tI5lFXBzGHvXShi',
          ),
          1 => 
          array (
            0 => 'shippingcompany',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::0AMm7Ra0PfjXTYdl',
          ),
          1 => 
          array (
            0 => 'shippingcompany',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'generated::bUzuFyDbEsF7mef8',
          ),
          1 => 
          array (
            0 => 'shippingcompany',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1105 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::wBZI0rgF6OBBRVif',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1130 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::P8mKtwCaMcT1t0yy',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1178 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::iTM4uN6AKlZvHvGy',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PATCH' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1201 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::8aNRRVdBxM0cwFLF',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1229 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'stock.show',
          ),
          1 => 
          array (
            0 => 'stock',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'stock.update',
          ),
          1 => 
          array (
            0 => 'stock',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'stock.destroy',
          ),
          1 => 
          array (
            0 => 'stock',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1264 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::GeORfObS9CFJbo81',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1273 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'purchases.show',
          ),
          1 => 
          array (
            0 => 'purchase',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'purchases.update',
          ),
          1 => 
          array (
            0 => 'purchase',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'purchases.destroy',
          ),
          1 => 
          array (
            0 => 'purchase',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1305 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::tvsAIXSJwEvjSW1p',
          ),
          1 => 
          array (
            0 => 'production',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::rdJLZ2HpVhSPOANk',
          ),
          1 => 
          array (
            0 => 'production',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'generated::9wRXCEeHFQElNu7F',
          ),
          1 => 
          array (
            0 => 'production',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1339 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::XzIVRxUJbPAPFAnm',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1384 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovements.show',
          ),
          1 => 
          array (
            0 => 'FactoryBankMovement',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovements.update',
          ),
          1 => 
          array (
            0 => 'FactoryBankMovement',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovements.destroy',
          ),
          1 => 
          array (
            0 => 'FactoryBankMovement',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1413 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovementsDetails.show',
          ),
          1 => 
          array (
            0 => 'FactoryBankMovementsDetail',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovementsDetails.update',
          ),
          1 => 
          array (
            0 => 'FactoryBankMovementsDetail',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovementsDetails.destroy',
          ),
          1 => 
          array (
            0 => 'FactoryBankMovementsDetail',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1442 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovementsCustody.show',
          ),
          1 => 
          array (
            0 => 'FactoryBankMovementsCustody',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovementsCustody.update',
          ),
          1 => 
          array (
            0 => 'FactoryBankMovementsCustody',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'FactoryBankMovementsCustody.destroy',
          ),
          1 => 
          array (
            0 => 'FactoryBankMovementsCustody',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1478 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'incomelist.show',
          ),
          1 => 
          array (
            0 => 'incomelist',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'incomelist.update',
          ),
          1 => 
          array (
            0 => 'incomelist',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'incomelist.destroy',
          ),
          1 => 
          array (
            0 => 'incomelist',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1501 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'incomes.show',
          ),
          1 => 
          array (
            0 => 'income',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'incomes.update',
          ),
          1 => 
          array (
            0 => 'income',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'incomes.destroy',
          ),
          1 => 
          array (
            0 => 'income',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1529 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::9Q0a4iImCNeUi4T7',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1558 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::aYAvwl3xTp38lgwe',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1591 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::578Ojx1T898J0WZX',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1621 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::C1rKAJUVaj34oYTG',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1657 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::cxOyb5OVjVaNGaOF',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1683 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::102Oz2KHawwL2WsT',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1713 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::wM1lJMwEvPdzJEga',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1736 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::bKib4TnB8FYWDQnb',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1767 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'expense.show',
          ),
          1 => 
          array (
            0 => 'expense',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'expense.update',
          ),
          1 => 
          array (
            0 => 'expense',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'expense.destroy',
          ),
          1 => 
          array (
            0 => 'expense',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1794 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'expense_kind.show',
          ),
          1 => 
          array (
            0 => 'expense_kind',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'expense_kind.update',
          ),
          1 => 
          array (
            0 => 'expense_kind',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'expense_kind.destroy',
          ),
          1 => 
          array (
            0 => 'expense_kind',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1832 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::wcjhJCm7vmGwnREI',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1862 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hLkGoZ8lAMyGryde',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1904 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::JkNLi7BqABeF40T5',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1924 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employees.show',
          ),
          1 => 
          array (
            0 => 'employee',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'employees.update',
          ),
          1 => 
          array (
            0 => 'employee',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'employees.destroy',
          ),
          1 => 
          array (
            0 => 'employee',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1957 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employeesubtraction.show',
          ),
          1 => 
          array (
            0 => 'employeesubtraction',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'employeesubtraction.update',
          ),
          1 => 
          array (
            0 => 'employeesubtraction',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'employeesubtraction.destroy',
          ),
          1 => 
          array (
            0 => 'employeesubtraction',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1988 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employeemerit.show',
          ),
          1 => 
          array (
            0 => 'employeemerit',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'employeemerit.update',
          ),
          1 => 
          array (
            0 => 'employeemerit',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'employeemerit.destroy',
          ),
          1 => 
          array (
            0 => 'employeemerit',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2018 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employeemonthpaid.show',
          ),
          1 => 
          array (
            0 => 'employeemonthpaid',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'employeemonthpaid.update',
          ),
          1 => 
          array (
            0 => 'employeemonthpaid',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'employeemonthpaid.destroy',
          ),
          1 => 
          array (
            0 => 'employeemonthpaid',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2055 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employeeadvancepayment.show',
          ),
          1 => 
          array (
            0 => 'employeeadvancepayment',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'employeeadvancepayment.update',
          ),
          1 => 
          array (
            0 => 'employeeadvancepayment',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'employeeadvancepayment.destroy',
          ),
          1 => 
          array (
            0 => 'employeeadvancepayment',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2092 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::rAvr3Sq0Iz2Zz2fI',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2118 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::tvLU5vVChgpkfbd2',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2152 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'assets.show',
          ),
          1 => 
          array (
            0 => 'asset',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'assets.update',
          ),
          1 => 
          array (
            0 => 'asset',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'assets.destroy',
          ),
          1 => 
          array (
            0 => 'asset',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2179 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::VmxYUGdA9GOw4U2I',
          ),
          1 => 
          array (
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      2209 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'approvals.show',
          ),
          1 => 
          array (
            0 => 'approval',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'approvals.update',
          ),
          1 => 
          array (
            0 => 'approval',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'approvals.destroy',
          ),
          1 => 
          array (
            0 => 'approval',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2241 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::8m1wadhmBjrw4XjN',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2274 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::60Wtm2zJKDGc5yt3',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2296 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::PkRCM4VFXXaV9vnm',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2339 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::gbs7sA9lXqk93ewU',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hkZe7i1qsgiDVYdg',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'generated::29AZj80EH3ePM76A',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2374 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::4NCQdYx9Cfiu48JW',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::BmhzphlMkVX8OlOQ',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'generated::tHQcJFjLgv32cJNG',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2404 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::51lyXdk06RMq43XJ',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::QEPQD2lVMgq86nVs',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'generated::tmpHcxg3BSAi6VR4',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2439 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::xo0T4XZiBdOFnAxA',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2475 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::boRsaB9g14i75X3P',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::XwfskbS6cXOOZPQW',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'generated::aUoJqvLenTkoNt6F',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2502 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::CeROVd8Qx9jqtmb8',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::JGlzpXtii0cz8dUL',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'generated::h4xogp1Ll2Eb8xQm',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2539 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::uZQ7LWYbmZpji0Hh',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::xO2sxctqW0DJwtOQ',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ClOlJdssH4cNUPTS',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2570 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::u7f40VvGgAEAKYjU',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      2595 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::j3rfG7EUt042687a',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      2622 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::potjrADmkKMeo18z',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      2655 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::BXAzVPpYtLQkFczk',
          ),
          1 => 
          array (
            0 => 'recipeId',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::eSCAYI6f77LSX4D7',
          ),
          1 => 
          array (
            0 => 'recipeId',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      2698 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Py8CmHBHz7lUy0F4',
          ),
          1 => 
          array (
            0 => 'recipeId',
            1 => 'extraCostId',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::lpyYCfiaYSGu5kHI',
          ),
          1 => 
          array (
            0 => 'recipeId',
            1 => 'extraCostId',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2726 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::QtXQqZqHdNn9lAtg',
          ),
          1 => 
          array (
            0 => 'recipeId',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      2757 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::J5k9Fuhfh1QMFWwP',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2784 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::QBAMMrRhSp9vYoa1',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2814 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Qqp85lWDZ5pbWmXu',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2853 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Wb20xyNVBS6gEeST',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2881 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::rCluNSm7UGWLi71W',
          ),
          1 => 
          array (
            0 => 'sheet',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2926 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::reLjefXDBomKWegU',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      2948 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Q1Q5FKuGoDKPAfCW',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PATCH' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      2969 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'lead.show',
          ),
          1 => 
          array (
            0 => 'lead',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'lead.update',
          ),
          1 => 
          array (
            0 => 'lead',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'lead.destroy',
          ),
          1 => 
          array (
            0 => 'lead',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      3006 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'lead-source.show',
          ),
          1 => 
          array (
            0 => 'lead_source',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'lead-source.update',
          ),
          1 => 
          array (
            0 => 'lead_source',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'lead-source.destroy',
          ),
          1 => 
          array (
            0 => 'lead_source',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      3038 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'lead-statuses.show',
          ),
          1 => 
          array (
            0 => 'lead_status',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'lead-statuses.update',
          ),
          1 => 
          array (
            0 => 'lead_status',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'lead-statuses.destroy',
          ),
          1 => 
          array (
            0 => 'lead_status',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      3054 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::BhaDyqgYB35VCLLq',
          ),
          1 => 
          array (
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      3077 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::KJouU4KCDPWzYtv5',
          ),
          1 => 
          array (
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      3090 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::pGorOCWthh7tor0z',
          ),
          1 => 
          array (
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      3117 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::2dpMvMPcvbSjI8fX',
          ),
          1 => 
          array (
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      3139 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::gYOQzSdfCdanlJDi',
          ),
          1 => 
          array (
            0 => 'lead',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      3166 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'lead-tool.show',
          ),
          1 => 
          array (
            0 => 'lead_tool',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'lead-tool.update',
          ),
          1 => 
          array (
            0 => 'lead_tool',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'lead-tool.destroy',
          ),
          1 => 
          array (
            0 => 'lead_tool',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      3196 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'lead-industry.show',
          ),
          1 => 
          array (
            0 => 'lead_industry',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'lead-industry.update',
          ),
          1 => 
          array (
            0 => 'lead_industry',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'lead-industry.destroy',
          ),
          1 => 
          array (
            0 => 'lead_industry',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      3241 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'ShippingLineStatement.show',
          ),
          1 => 
          array (
            0 => 'ShippingLineStatement',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'ShippingLineStatement.update',
          ),
          1 => 
          array (
            0 => 'ShippingLineStatement',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'ShippingLineStatement.destroy',
          ),
          1 => 
          array (
            0 => 'ShippingLineStatement',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      3263 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::aU1ieTANgOFFEsxO',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      3294 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::WBn43S28LthYhhtj',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      3347 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::HBjxeeGBJmybDBeQ',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      3356 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'tree_account.show',
          ),
          1 => 
          array (
            0 => 'tree_account',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'tree_account.update',
          ),
          1 => 
          array (
            0 => 'tree_account',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'tree_account.destroy',
          ),
          1 => 
          array (
            0 => 'tree_account',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        3 => 
        array (
          0 => NULL,
          1 => NULL,
          2 => NULL,
          3 => NULL,
          4 => false,
          5 => false,
          6 => 0,
        ),
      ),
    ),
    4 => NULL,
  ),
  'attributes' => 
  array (
    'generated::0W9fHg7WQcfK6Cnm' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'laravel-websockets',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Middleware\\Authorize',
        ),
        'uses' => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Controllers\\ShowDashboard@__invoke',
        'controller' => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Controllers\\ShowDashboard',
        'namespace' => NULL,
        'prefix' => 'laravel-websockets',
        'where' => 
        array (
        ),
        'as' => 'generated::0W9fHg7WQcfK6Cnm',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::7cAi72TtXmfm0hFA' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'laravel-websockets/api/{appId}/statistics',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Middleware\\Authorize',
        ),
        'uses' => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Controllers\\DashboardApiController@getStatistics',
        'controller' => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Controllers\\DashboardApiController@getStatistics',
        'namespace' => NULL,
        'prefix' => 'laravel-websockets',
        'where' => 
        array (
        ),
        'as' => 'generated::7cAi72TtXmfm0hFA',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::dvu2VMymvS8wT3WB' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'laravel-websockets/auth',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Middleware\\Authorize',
        ),
        'uses' => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Controllers\\AuthenticateDashboard@__invoke',
        'controller' => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Controllers\\AuthenticateDashboard',
        'namespace' => NULL,
        'prefix' => 'laravel-websockets',
        'where' => 
        array (
        ),
        'as' => 'generated::dvu2VMymvS8wT3WB',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::pLh481jvpU4G3gDk' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'laravel-websockets/event',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Middleware\\Authorize',
        ),
        'uses' => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Controllers\\SendMessage@__invoke',
        'controller' => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Controllers\\SendMessage',
        'namespace' => NULL,
        'prefix' => 'laravel-websockets',
        'where' => 
        array (
        ),
        'as' => 'generated::pLh481jvpU4G3gDk',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::i1BobBpuEskZCfqs' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'laravel-websockets/statistics',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'BeyondCode\\LaravelWebSockets\\Statistics\\Http\\Middleware\\Authorize',
        ),
        'uses' => 'BeyondCode\\LaravelWebSockets\\Statistics\\Http\\Controllers\\WebSocketStatisticsEntriesController@store',
        'controller' => 'BeyondCode\\LaravelWebSockets\\Statistics\\Http\\Controllers\\WebSocketStatisticsEntriesController@store',
        'namespace' => NULL,
        'prefix' => 'laravel-websockets',
        'where' => 
        array (
        ),
        'as' => 'generated::i1BobBpuEskZCfqs',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'sanctum.csrf-cookie' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'sanctum/csrf-cookie',
      'action' => 
      array (
        'uses' => 'Laravel\\Sanctum\\Http\\Controllers\\CsrfCookieController@show',
        'controller' => 'Laravel\\Sanctum\\Http\\Controllers\\CsrfCookieController@show',
        'namespace' => NULL,
        'prefix' => 'sanctum',
        'where' => 
        array (
        ),
        'middleware' => 
        array (
          0 => 'web',
        ),
        'as' => 'sanctum.csrf-cookie',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'ignition.healthCheck' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => '_ignition/health-check',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'Spatie\\LaravelIgnition\\Http\\Middleware\\RunnableSolutionsEnabled',
        ),
        'uses' => 'Spatie\\LaravelIgnition\\Http\\Controllers\\HealthCheckController@__invoke',
        'controller' => 'Spatie\\LaravelIgnition\\Http\\Controllers\\HealthCheckController',
        'as' => 'ignition.healthCheck',
        'namespace' => NULL,
        'prefix' => '_ignition',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'ignition.executeSolution' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => '_ignition/execute-solution',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'Spatie\\LaravelIgnition\\Http\\Middleware\\RunnableSolutionsEnabled',
        ),
        'uses' => 'Spatie\\LaravelIgnition\\Http\\Controllers\\ExecuteSolutionController@__invoke',
        'controller' => 'Spatie\\LaravelIgnition\\Http\\Controllers\\ExecuteSolutionController',
        'as' => 'ignition.executeSolution',
        'namespace' => NULL,
        'prefix' => '_ignition',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'ignition.updateConfig' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => '_ignition/update-config',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'Spatie\\LaravelIgnition\\Http\\Middleware\\RunnableSolutionsEnabled',
        ),
        'uses' => 'Spatie\\LaravelIgnition\\Http\\Controllers\\UpdateConfigController@__invoke',
        'controller' => 'Spatie\\LaravelIgnition\\Http\\Controllers\\UpdateConfigController',
        'as' => 'ignition.updateConfig',
        'namespace' => NULL,
        'prefix' => '_ignition',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::YClSb4J2YQlutGiA' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'POST',
        2 => 'HEAD',
      ),
      'uri' => 'broadcasting/auth',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
        ),
        'uses' => '\\Illuminate\\Broadcasting\\BroadcastController@authenticate',
        'controller' => '\\Illuminate\\Broadcasting\\BroadcastController@authenticate',
        'namespace' => NULL,
        'prefix' => '',
        'where' => 
        array (
        ),
        'excluded_middleware' => 
        array (
          0 => 'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken',
        ),
        'as' => 'generated::YClSb4J2YQlutGiA',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::fyfSNfIabnFbPkMr' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/meta-test',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'O:47:"Laravel\\SerializableClosure\\SerializableClosure":1:{s:12:"serializable";O:46:"Laravel\\SerializableClosure\\Serializers\\Signed":2:{s:12:"serializable";s:351:"O:46:"Laravel\\SerializableClosure\\Serializers\\Native":5:{s:3:"use";a:0:{}s:8:"function";s:132:"function () {
    return \\app(\\App\\Services\\MetaWhatsAppService::class)
        ->sendMessage(\'201068704455\', \'Test from server\');
}";s:5:"scope";s:37:"Illuminate\\Routing\\RouteFileRegistrar";s:4:"this";N;s:4:"self";s:32:"000000000000062b0000000000000000";}";s:4:"hash";s:44:"jzEiU4vh3Qrj3EFfjy88je9LfXRhfItQjYNE9XlMKXM=";}}',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::fyfSNfIabnFbPkMr',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::3VLxbMOp1x1FZtPI' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/test',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'O:47:"Laravel\\SerializableClosure\\SerializableClosure":1:{s:12:"serializable";O:46:"Laravel\\SerializableClosure\\Serializers\\Signed":2:{s:12:"serializable";s:283:"O:46:"Laravel\\SerializableClosure\\Serializers\\Native":5:{s:3:"use";a:0:{}s:8:"function";s:65:"function () {
    return \\response()->json([\'status\' => \'ok\']);
}";s:5:"scope";s:37:"Illuminate\\Routing\\RouteFileRegistrar";s:4:"this";N;s:4:"self";s:32:"000000000000062d0000000000000000";}";s:4:"hash";s:44:"6HV2oIx3ApQit/gIO6j8Hcy/+ncwSprHoSKyhMB8ZRY=";}}',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::3VLxbMOp1x1FZtPI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::k2eaz1X36kT6RyU0' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/meta/webhook',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\MetaWebhookController@verify',
        'controller' => 'App\\Http\\Controllers\\MetaWebhookController@verify',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::k2eaz1X36kT6RyU0',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::5WsTAv2ZOnWO795L' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/meta/webhook',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\MetaWebhookController@handle',
        'controller' => 'App\\Http\\Controllers\\MetaWebhookController@handle',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::5WsTAv2ZOnWO795L',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Xr1dwJhRgyWEF5WP' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/meta/webhook-health',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'O:47:"Laravel\\SerializableClosure\\SerializableClosure":1:{s:12:"serializable";O:46:"Laravel\\SerializableClosure\\Serializers\\Signed":2:{s:12:"serializable";s:1142:"O:46:"Laravel\\SerializableClosure\\Serializers\\Native":5:{s:3:"use";a:0:{}s:8:"function";s:923:"function () {
    $hasMediaId = \\Illuminate\\Support\\Facades\\Schema::hasColumn(\'messages\', \'media_id\');
    $hasType = \\Illuminate\\Support\\Facades\\Schema::hasColumn(\'messages\', \'type\');
    $hasPhoneNumberId = \\Illuminate\\Support\\Facades\\Schema::hasColumn(\'messages\', \'phone_number_id\');
    $latestInbound = \\App\\Models\\Message::where(\'direction\', \'inbound\')
        ->orderByDesc(\'id\')
        ->first([\'id\', \'type\', \'media_id\', \'content\', \'created_at\']);
    $totalMedia = \\App\\Models\\Message::whereNotNull(\'media_id\')
        ->where(\'media_id\', \'!=\', \'\')
        ->count();
    return \\response()->json([
        \'columns_ok\' => $hasMediaId && $hasType && $hasPhoneNumberId,
        \'has_media_id_column\' => $hasMediaId,
        \'has_type_column\' => $hasType,
        \'has_phone_number_id_column\' => $hasPhoneNumberId,
        \'total_media_messages\' => $totalMedia,
        \'latest_inbound\' => $latestInbound,
    ]);
}";s:5:"scope";s:37:"Illuminate\\Routing\\RouteFileRegistrar";s:4:"this";N;s:4:"self";s:32:"00000000000006310000000000000000";}";s:4:"hash";s:44:"RxATG/hoS3oCvFCQrguaKVNFm81QyyGsM8EG+DpZugU=";}}',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Xr1dwJhRgyWEF5WP',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::qsyBTI6LdtIsW4Cj' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/shopify/webhook',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\ShopifyWebhookController@handle',
        'controller' => 'App\\Http\\Controllers\\ShopifyWebhookController@handle',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::qsyBTI6LdtIsW4Cj',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::o81Eca56pneEFyES' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/webhooks/shipping/update',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingPartnerWebhookController@update',
        'controller' => 'App\\Http\\Controllers\\ShippingPartnerWebhookController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::o81Eca56pneEFyES',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::RcaZovAzdKLjXAZC' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/auth/login',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthController@login',
        'controller' => 'App\\Http\\Controllers\\AuthController@login',
        'namespace' => NULL,
        'prefix' => 'api/auth',
        'where' => 
        array (
        ),
        'as' => 'generated::RcaZovAzdKLjXAZC',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::J7G1pUbAd8Qh2aTK' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/auth/logout',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthController@logout',
        'controller' => 'App\\Http\\Controllers\\AuthController@logout',
        'namespace' => NULL,
        'prefix' => 'api/auth',
        'where' => 
        array (
        ),
        'as' => 'generated::J7G1pUbAd8Qh2aTK',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::rBx7oZKTfQpKhmQG' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/auth/refresh',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthController@refresh',
        'controller' => 'App\\Http\\Controllers\\AuthController@refresh',
        'namespace' => NULL,
        'prefix' => 'api/auth',
        'where' => 
        array (
        ),
        'as' => 'generated::rBx7oZKTfQpKhmQG',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::0aRXBGCOu2e4pdv0' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/auth/me',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthController@me',
        'controller' => 'App\\Http\\Controllers\\AuthController@me',
        'namespace' => NULL,
        'prefix' => 'api/auth',
        'where' => 
        array (
        ),
        'as' => 'generated::0aRXBGCOu2e4pdv0',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::s6tZNXmclDkxixpA' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/whatsapp/webhook',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@whatsapp',
        'controller' => 'App\\Http\\Controllers\\OrdersController@whatsapp',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::s6tZNXmclDkxixpA',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::lPjE9AdbEu67EjGR' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/usersnotification',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\UserController@usersForNotification',
        'controller' => 'App\\Http\\Controllers\\UserController@usersForNotification',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::lPjE9AdbEu67EjGR',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::1QEFR2WT5lSH3bEX' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/notification',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\NotificationController@sendNotification',
        'controller' => 'App\\Http\\Controllers\\NotificationController@sendNotification',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::1QEFR2WT5lSH3bEX',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::UhthlrWx2VQzZNoy' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/notification',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\NotificationController@getById',
        'controller' => 'App\\Http\\Controllers\\NotificationController@getById',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::UhthlrWx2VQzZNoy',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Kx7wfP2ROYShlLOc' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/recievednotification',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\NotificationController@recievedNotifiy',
        'controller' => 'App\\Http\\Controllers\\NotificationController@recievedNotifiy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Kx7wfP2ROYShlLOc',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::WzZELDr7kL4khxAi' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/sentnotification',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\NotificationController@sentNotifiy',
        'controller' => 'App\\Http\\Controllers\\NotificationController@sentNotifiy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::WzZELDr7kL4khxAi',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::GXG9HnwINcDQxxtv' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/notification/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\NotificationController@readNotify',
        'controller' => 'App\\Http\\Controllers\\NotificationController@readNotify',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::GXG9HnwINcDQxxtv',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::xcOtDF9jvteBhLPe' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/notification/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\NotificationController@readOrderNotify',
        'controller' => 'App\\Http\\Controllers\\NotificationController@readOrderNotify',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::xcOtDF9jvteBhLPe',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ECsosy8zAGH4WhrW' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/conversations/{conversation_id}/messages',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\ConversationController@messages',
        'controller' => 'App\\Http\\Controllers\\ConversationController@messages',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ECsosy8zAGH4WhrW',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'conversation_id' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::JPiobp8oBCCReo6q' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/media/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\ConversationController@media',
        'controller' => 'App\\Http\\Controllers\\ConversationController@media',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::JPiobp8oBCCReo6q',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'id' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::HanSOerM66k2x8sb' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/whatsapp/send',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@sendMessage',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@sendMessage',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::HanSOerM66k2x8sb',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::p9Cp3YYLiRzH84fp' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/whatsapp/send-template',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@sendTemplateMessage',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@sendTemplateMessage',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::p9Cp3YYLiRzH84fp',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::6AtZJENCOVeXZM86' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/whatsapp/send-from-order',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@sendMessageFromOrder',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@sendMessageFromOrder',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::6AtZJENCOVeXZM86',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::qP9no81VGKOGWr8u' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/whatsapp/meta-templates',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@getMetaTemplatesList',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@getMetaTemplatesList',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::qP9no81VGKOGWr8u',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ioGR5goXfDx95xWT' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/whatsapp/send-meta-template-from-order',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@sendMetaTemplateFromOrder',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@sendMetaTemplateFromOrder',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::ioGR5goXfDx95xWT',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ybrqMCnN1ZIZAjYG' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/whatsapp/chat/{customerId}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@getChatMessages',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@getChatMessages',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::ybrqMCnN1ZIZAjYG',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ouoCRQ1XIQy9cKei' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/whatsapp/customers/by-phone',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@findCustomerByPhone',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@findCustomerByPhone',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::ouoCRQ1XIQy9cKei',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::YJJN13nBKWPgZKpd' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/whatsapp/customers/whatsapp-snippet',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@getWhatsAppSnippet',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@getWhatsAppSnippet',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::YJJN13nBKWPgZKpd',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::nb09lQvcb6tNQAVl' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/whatsapp/customers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@getCustomers',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@getCustomers',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::nb09lQvcb6tNQAVl',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Dfn2tv6HRXHgL4DN' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/whatsapp/templates',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@getTemplates',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@getTemplates',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::Dfn2tv6HRXHgL4DN',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::LMeXTckqva7M18zJ' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/whatsapp/templates',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@createTemplate',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@createTemplate',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::LMeXTckqva7M18zJ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::W875CTnGHS2C6P5B' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/whatsapp/phone-numbers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@getAvailablePhoneNumbers',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@getAvailablePhoneNumbers',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::W875CTnGHS2C6P5B',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::igKXAgSn1kPxOSvt' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/whatsapp/assignments',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@getAllAssignments',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@getAllAssignments',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::igKXAgSn1kPxOSvt',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::VCgxkmg4UIu7lBzK' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/whatsapp/user-phone-numbers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@getUserPhoneNumbers',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@getUserPhoneNumbers',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::VCgxkmg4UIu7lBzK',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::C4zO7TPrUQX2t7Gl' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/whatsapp/assign-users',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@assignUsersToPhoneNumber',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@assignUsersToPhoneNumber',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::C4zO7TPrUQX2t7Gl',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ociwDgrKyRUxv1hH' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/whatsapp/remove-assignment',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\WhatsAppMessageController@removeUserAssignment',
        'controller' => 'App\\Http\\Controllers\\WhatsAppMessageController@removeUserAssignment',
        'namespace' => NULL,
        'prefix' => 'api/whatsapp',
        'where' => 
        array (
        ),
        'as' => 'generated::ociwDgrKyRUxv1hH',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::U9H0SZzUbEOuscI9' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/order_source',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\OrderSourceController@index',
        'controller' => 'App\\Http\\Controllers\\OrderSourceController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::U9H0SZzUbEOuscI9',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::m5T6g51WZ7aOd07n' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shipping_methods',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingMethodsController@index',
        'controller' => 'App\\Http\\Controllers\\ShippingMethodsController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::m5T6g51WZ7aOd07n',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::klURRbi4CGFOyHPy' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shippinglines',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingLineController@index',
        'controller' => 'App\\Http\\Controllers\\ShippingLineController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::klURRbi4CGFOyHPy',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hby7mHF33XgwQQIT' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/orders/search',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@search',
        'controller' => 'App\\Http\\Controllers\\OrdersController@search',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hby7mHF33XgwQQIT',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::b5MaLQWwp1dOhggT' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/orders/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@show',
        'controller' => 'App\\Http\\Controllers\\OrdersController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::b5MaLQWwp1dOhggT',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hmntJYciWwNTuYsf' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/productions',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\ProductionController@index',
        'controller' => 'App\\Http\\Controllers\\ProductionController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hmntJYciWwNTuYsf',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::jrAkTd754bhmyaP1' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shippingcompanySelect',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingCompanyController@shippingcompanySelect',
        'controller' => 'App\\Http\\Controllers\\ShippingCompanyController@shippingcompanySelect',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::jrAkTd754bhmyaP1',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hIXd3ikcJ7OXY4Le' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/bankSelect',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\BanksController@bankSelect',
        'controller' => 'App\\Http\\Controllers\\BanksController@bankSelect',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hIXd3ikcJ7OXY4Le',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::MaA3bmbIqqt9yPzA' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categories/cat_orders',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@categories_for_orders',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@categories_for_orders',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::MaA3bmbIqqt9yPzA',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hxpzSK4FyoFF3Eaj' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/reports/categoriesSellReports',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@categoriesSellReports',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@categoriesSellReports',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hxpzSK4FyoFF3Eaj',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::IC4R9ABabICUPieD' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/reports/shipping-companies',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingCompanyController@shippingCompaniesReport',
        'controller' => 'App\\Http\\Controllers\\ShippingCompanyController@shippingCompaniesReport',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::IC4R9ABabICUPieD',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::jeXG0X0BIcQAs9GW' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/transactions/by-supplier-order/search',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\SupplierController@supplierAccountsAggregated',
        'controller' => 'App\\Http\\Controllers\\SupplierController@supplierAccountsAggregated',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::jeXG0X0BIcQAs9GW',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::mCW6t2MFaucwz6WD' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categories/warehouse_balance',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@warehouse_balance',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@warehouse_balance',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::mCW6t2MFaucwz6WD',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Q7UGZOfNLP4M7zGI' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categories/categories_details/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@categories_details',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@categories_details',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Q7UGZOfNLP4M7zGI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::xv6BhUi0rGMCHfBP' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categories/warehousedetails',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@warehouseDetails',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@warehouseDetails',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::xv6BhUi0rGMCHfBP',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::H9zL7FtembeOT8R3' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categories/categoryDetailsByWherehouse',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@categoryDetailsByWherehouse',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@categoryDetailsByWherehouse',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::H9zL7FtembeOT8R3',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::94GSZAFa7wseD7gV' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categoryquantity',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@changeCategoryQuantity',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@changeCategoryQuantity',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::94GSZAFa7wseD7gV',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::rCJmrJwAAXRx80yC' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categories/monthlyinventory',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@monthlyInventory',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@monthlyInventory',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::rCJmrJwAAXRx80yC',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::UOvhxavWdKgmqDyI' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categories/monthlyInventoryDetailsByWherehouse',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@monthlyInventoryDetailsByWherehouse',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@monthlyInventoryDetailsByWherehouse',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::UOvhxavWdKgmqDyI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Xf0ZWwNre5xxWCEz' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categories/categoryByWarehouse',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@categoryByWarehouse',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@categoryByWarehouse',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Xf0ZWwNre5xxWCEz',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::qNkNgkyej9gHMAup' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/suppliers/search',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\SupplierController@search',
        'controller' => 'App\\Http\\Controllers\\SupplierController@search',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::qNkNgkyej9gHMAup',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::WfkiNNZLBVC14iaC' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/suppliers/StoreSupplierType',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\SupplierController@StoreSupplierType',
        'controller' => 'App\\Http\\Controllers\\SupplierController@StoreSupplierType',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::WfkiNNZLBVC14iaC',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::wxUVfyNJHbefyiga' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/suppliers/deleteType/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\SupplierController@deleteType',
        'controller' => 'App\\Http\\Controllers\\SupplierController@deleteType',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::wxUVfyNJHbefyiga',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::eSwf25lCLkc7Ffkk' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/suppliers/supplierPay/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\SupplierController@supplierPay',
        'controller' => 'App\\Http\\Controllers\\SupplierController@supplierPay',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::eSwf25lCLkc7Ffkk',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::VNn0Jq2nkcm6yX99' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/suppliers/getAllSupplierTypes',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\SupplierController@getAllSupplierTypes',
        'controller' => 'App\\Http\\Controllers\\SupplierController@getAllSupplierTypes',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::VNn0Jq2nkcm6yX99',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::L1osqre55P0uoHdb' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/suppliers/supplierDetails/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\SupplierController@supplier_details',
        'controller' => 'App\\Http\\Controllers\\SupplierController@supplier_details',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::L1osqre55P0uoHdb',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ueTRwcbmeqag5yLC' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/suppliers/supplier_names',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\SupplierController@supplier_names',
        'controller' => 'App\\Http\\Controllers\\SupplierController@supplier_names',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ueTRwcbmeqag5yLC',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'suppliers.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/suppliers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'suppliers.index',
        'uses' => 'App\\Http\\Controllers\\SupplierController@index',
        'controller' => 'App\\Http\\Controllers\\SupplierController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'suppliers.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/suppliers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'suppliers.store',
        'uses' => 'App\\Http\\Controllers\\SupplierController@store',
        'controller' => 'App\\Http\\Controllers\\SupplierController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'suppliers.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/suppliers/{supplier}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'suppliers.show',
        'uses' => 'App\\Http\\Controllers\\SupplierController@show',
        'controller' => 'App\\Http\\Controllers\\SupplierController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'suppliers.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/suppliers/{supplier}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'suppliers.update',
        'uses' => 'App\\Http\\Controllers\\SupplierController@update',
        'controller' => 'App\\Http\\Controllers\\SupplierController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'suppliers.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/suppliers/{supplier}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'suppliers.destroy',
        'uses' => 'App\\Http\\Controllers\\SupplierController@destroy',
        'controller' => 'App\\Http\\Controllers\\SupplierController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::UyHegoSfYW138hqs' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/inventory/import/items',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\Inventory\\InventoryExcelImportController@importItems',
        'controller' => 'App\\Http\\Controllers\\Inventory\\InventoryExcelImportController@importItems',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::UyHegoSfYW138hqs',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::6GauUWLd111sKASl' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/inventory/import/opening-balances',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\Inventory\\InventoryExcelImportController@importOpeningBalances',
        'controller' => 'App\\Http\\Controllers\\Inventory\\InventoryExcelImportController@importOpeningBalances',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::6GauUWLd111sKASl',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::m7Z3lrBVE2iXrQ2z' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/inventory/import/adjustments',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\Inventory\\InventoryExcelImportController@importAdjustments',
        'controller' => 'App\\Http\\Controllers\\Inventory\\InventoryExcelImportController@importAdjustments',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::m7Z3lrBVE2iXrQ2z',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::d13e98TDsxckOZxq' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/purchases/search',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\PurchasesController@search',
        'controller' => 'App\\Http\\Controllers\\PurchasesController@search',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::d13e98TDsxckOZxq',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::GeORfObS9CFJbo81' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/purchases/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\PurchasesController@show',
        'controller' => 'App\\Http\\Controllers\\PurchasesController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::GeORfObS9CFJbo81',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'purchases.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/purchases',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'purchases.index',
        'uses' => 'App\\Http\\Controllers\\PurchasesController@index',
        'controller' => 'App\\Http\\Controllers\\PurchasesController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'purchases.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/purchases',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'purchases.store',
        'uses' => 'App\\Http\\Controllers\\PurchasesController@store',
        'controller' => 'App\\Http\\Controllers\\PurchasesController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'purchases.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/purchases/{purchase}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'purchases.show',
        'uses' => 'App\\Http\\Controllers\\PurchasesController@show',
        'controller' => 'App\\Http\\Controllers\\PurchasesController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'purchases.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/purchases/{purchase}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'purchases.update',
        'uses' => 'App\\Http\\Controllers\\PurchasesController@update',
        'controller' => 'App\\Http\\Controllers\\PurchasesController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'purchases.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/purchases/{purchase}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'purchases.destroy',
        'uses' => 'App\\Http\\Controllers\\PurchasesController@destroy',
        'controller' => 'App\\Http\\Controllers\\PurchasesController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::OLc0JDaNudVBSvEH' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/banks',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\BanksController@index',
        'controller' => 'App\\Http\\Controllers\\BanksController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::OLc0JDaNudVBSvEH',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovements.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/FactoryBankMovements',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovements.index',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsController@index',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovements.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/FactoryBankMovements',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovements.store',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsController@store',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovements.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/FactoryBankMovements/{FactoryBankMovement}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovements.show',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsController@show',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovements.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/FactoryBankMovements/{FactoryBankMovement}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovements.update',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsController@update',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovements.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/FactoryBankMovements/{FactoryBankMovement}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovements.destroy',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsController@destroy',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovementsDetails.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/FactoryBankMovementsDetails',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovementsDetails.index',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsDetailsController@index',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsDetailsController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovementsDetails.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/FactoryBankMovementsDetails',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovementsDetails.store',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsDetailsController@store',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsDetailsController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovementsDetails.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/FactoryBankMovementsDetails/{FactoryBankMovementsDetail}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovementsDetails.show',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsDetailsController@show',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsDetailsController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovementsDetails.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/FactoryBankMovementsDetails/{FactoryBankMovementsDetail}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovementsDetails.update',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsDetailsController@update',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsDetailsController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovementsDetails.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/FactoryBankMovementsDetails/{FactoryBankMovementsDetail}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovementsDetails.destroy',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsDetailsController@destroy',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsDetailsController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovementsCustody.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/FactoryBankMovementsCustody',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovementsCustody.index',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsCustodyController@index',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsCustodyController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovementsCustody.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/FactoryBankMovementsCustody',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovementsCustody.store',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsCustodyController@store',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsCustodyController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovementsCustody.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/FactoryBankMovementsCustody/{FactoryBankMovementsCustody}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovementsCustody.show',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsCustodyController@show',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsCustodyController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovementsCustody.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/FactoryBankMovementsCustody/{FactoryBankMovementsCustody}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovementsCustody.update',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsCustodyController@update',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsCustodyController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'FactoryBankMovementsCustody.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/FactoryBankMovementsCustody/{FactoryBankMovementsCustody}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'FactoryBankMovementsCustody.destroy',
        'uses' => 'App\\Http\\Controllers\\FactoryBankMovementsCustodyController@destroy',
        'controller' => 'App\\Http\\Controllers\\FactoryBankMovementsCustodyController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'incomelist.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/incomelist',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'incomelist.index',
        'uses' => 'App\\Http\\Controllers\\IncomeListController@index',
        'controller' => 'App\\Http\\Controllers\\IncomeListController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'incomelist.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/incomelist',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'incomelist.store',
        'uses' => 'App\\Http\\Controllers\\IncomeListController@store',
        'controller' => 'App\\Http\\Controllers\\IncomeListController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'incomelist.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/incomelist/{incomelist}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'incomelist.show',
        'uses' => 'App\\Http\\Controllers\\IncomeListController@show',
        'controller' => 'App\\Http\\Controllers\\IncomeListController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'incomelist.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/incomelist/{incomelist}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'incomelist.update',
        'uses' => 'App\\Http\\Controllers\\IncomeListController@update',
        'controller' => 'App\\Http\\Controllers\\IncomeListController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'incomelist.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/incomelist/{incomelist}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'incomelist.destroy',
        'uses' => 'App\\Http\\Controllers\\IncomeListController@destroy',
        'controller' => 'App\\Http\\Controllers\\IncomeListController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::9Q0a4iImCNeUi4T7' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/banks/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\BanksController@show',
        'controller' => 'App\\Http\\Controllers\\BanksController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::9Q0a4iImCNeUi4T7',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::R00DMY1Eauoco2Fq' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/banks',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\BanksController@store',
        'controller' => 'App\\Http\\Controllers\\BanksController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::R00DMY1Eauoco2Fq',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::aYAvwl3xTp38lgwe' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/banks/depositbank/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\BanksController@depositBank',
        'controller' => 'App\\Http\\Controllers\\BanksController@depositBank',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::aYAvwl3xTp38lgwe',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::578Ojx1T898J0WZX' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/banks/editBankBalance/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\BanksController@editBankBalance',
        'controller' => 'App\\Http\\Controllers\\BanksController@editBankBalance',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::578Ojx1T898J0WZX',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::C1rKAJUVaj34oYTG' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/banks/withDrawBank/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\BanksController@withDrawBank',
        'controller' => 'App\\Http\\Controllers\\BanksController@withDrawBank',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::C1rKAJUVaj34oYTG',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::kHefBGYObUL61C30' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/bank/transfermoney',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\BanksController@transferMoney',
        'controller' => 'App\\Http\\Controllers\\BanksController@transferMoney',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::kHefBGYObUL61C30',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::a4hf7amjA2ClobRi' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/expense/search',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ExpenseController@search',
        'controller' => 'App\\Http\\Controllers\\ExpenseController@search',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::a4hf7amjA2ClobRi',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::cxOyb5OVjVaNGaOF' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/editexpense/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ExpenseController@editExpense',
        'controller' => 'App\\Http\\Controllers\\ExpenseController@editExpense',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::cxOyb5OVjVaNGaOF',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::rAvr3Sq0Iz2Zz2fI' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/deleteexpense/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ExpenseController@deleteExpense',
        'controller' => 'App\\Http\\Controllers\\ExpenseController@deleteExpense',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::rAvr3Sq0Iz2Zz2fI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'expense.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/expense',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'expense.index',
        'uses' => 'App\\Http\\Controllers\\ExpenseController@index',
        'controller' => 'App\\Http\\Controllers\\ExpenseController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'expense.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/expense',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'expense.store',
        'uses' => 'App\\Http\\Controllers\\ExpenseController@store',
        'controller' => 'App\\Http\\Controllers\\ExpenseController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'expense.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/expense/{expense}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'expense.show',
        'uses' => 'App\\Http\\Controllers\\ExpenseController@show',
        'controller' => 'App\\Http\\Controllers\\ExpenseController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'expense.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/expense/{expense}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'expense.update',
        'uses' => 'App\\Http\\Controllers\\ExpenseController@update',
        'controller' => 'App\\Http\\Controllers\\ExpenseController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'expense.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/expense/{expense}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'expense.destroy',
        'uses' => 'App\\Http\\Controllers\\ExpenseController@destroy',
        'controller' => 'App\\Http\\Controllers\\ExpenseController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::oemgNb8LiZzLBizI' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/expense_kind/search',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ExpenseKindController@search',
        'controller' => 'App\\Http\\Controllers\\ExpenseKindController@search',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::oemgNb8LiZzLBizI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'expense_kind.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/expense_kind',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'expense_kind.index',
        'uses' => 'App\\Http\\Controllers\\ExpenseKindController@index',
        'controller' => 'App\\Http\\Controllers\\ExpenseKindController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'expense_kind.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/expense_kind',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'expense_kind.store',
        'uses' => 'App\\Http\\Controllers\\ExpenseKindController@store',
        'controller' => 'App\\Http\\Controllers\\ExpenseKindController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'expense_kind.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/expense_kind/{expense_kind}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'expense_kind.show',
        'uses' => 'App\\Http\\Controllers\\ExpenseKindController@show',
        'controller' => 'App\\Http\\Controllers\\ExpenseKindController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'expense_kind.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/expense_kind/{expense_kind}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'expense_kind.update',
        'uses' => 'App\\Http\\Controllers\\ExpenseKindController@update',
        'controller' => 'App\\Http\\Controllers\\ExpenseKindController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'expense_kind.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/expense_kind/{expense_kind}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'expense_kind.destroy',
        'uses' => 'App\\Http\\Controllers\\ExpenseKindController@destroy',
        'controller' => 'App\\Http\\Controllers\\ExpenseKindController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'assets.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/assets',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'assets.index',
        'uses' => 'App\\Http\\Controllers\\AssetController@index',
        'controller' => 'App\\Http\\Controllers\\AssetController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'assets.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/assets',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'assets.store',
        'uses' => 'App\\Http\\Controllers\\AssetController@store',
        'controller' => 'App\\Http\\Controllers\\AssetController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'assets.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/assets/{asset}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'assets.show',
        'uses' => 'App\\Http\\Controllers\\AssetController@show',
        'controller' => 'App\\Http\\Controllers\\AssetController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'assets.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/assets/{asset}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'assets.update',
        'uses' => 'App\\Http\\Controllers\\AssetController@update',
        'controller' => 'App\\Http\\Controllers\\AssetController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'assets.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/assets/{asset}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'assets.destroy',
        'uses' => 'App\\Http\\Controllers\\AssetController@destroy',
        'controller' => 'App\\Http\\Controllers\\AssetController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::VmxYUGdA9GOw4U2I' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/assets/run-depreciation',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\DepreciationController@runDepreciation',
        'controller' => 'App\\Http\\Controllers\\DepreciationController@runDepreciation',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::VmxYUGdA9GOw4U2I',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::eooL5D4CwGbr7zrp' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/cimmitments/{id}/pay',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CimmitmentController@pay',
        'controller' => 'App\\Http\\Controllers\\CimmitmentController@pay',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::eooL5D4CwGbr7zrp',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'cimmitments.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/cimmitments',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'cimmitments.index',
        'uses' => 'App\\Http\\Controllers\\CimmitmentController@index',
        'controller' => 'App\\Http\\Controllers\\CimmitmentController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'cimmitments.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/cimmitments',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'cimmitments.store',
        'uses' => 'App\\Http\\Controllers\\CimmitmentController@store',
        'controller' => 'App\\Http\\Controllers\\CimmitmentController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'cimmitments.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/cimmitments/{cimmitment}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'cimmitments.show',
        'uses' => 'App\\Http\\Controllers\\CimmitmentController@show',
        'controller' => 'App\\Http\\Controllers\\CimmitmentController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'cimmitments.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/cimmitments/{cimmitment}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'cimmitments.update',
        'uses' => 'App\\Http\\Controllers\\CimmitmentController@update',
        'controller' => 'App\\Http\\Controllers\\CimmitmentController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'cimmitments.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/cimmitments/{cimmitment}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'cimmitments.destroy',
        'uses' => 'App\\Http\\Controllers\\CimmitmentController@destroy',
        'controller' => 'App\\Http\\Controllers\\CimmitmentController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hDQl6Uu44wK8wepT' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/covenants',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CovenantController@index',
        'controller' => 'App\\Http\\Controllers\\CovenantController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hDQl6Uu44wK8wepT',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::HXFrIRiZh5gbQvy1' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/covenants',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CovenantController@store',
        'controller' => 'App\\Http\\Controllers\\CovenantController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::HXFrIRiZh5gbQvy1',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'incomes.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/incomes',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'incomes.index',
        'uses' => 'App\\Http\\Controllers\\IncomeController@index',
        'controller' => 'App\\Http\\Controllers\\IncomeController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'incomes.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/incomes',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'incomes.store',
        'uses' => 'App\\Http\\Controllers\\IncomeController@store',
        'controller' => 'App\\Http\\Controllers\\IncomeController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'incomes.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/incomes/{income}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'incomes.show',
        'uses' => 'App\\Http\\Controllers\\IncomeController@show',
        'controller' => 'App\\Http\\Controllers\\IncomeController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'incomes.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/incomes/{income}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'incomes.update',
        'uses' => 'App\\Http\\Controllers\\IncomeController@update',
        'controller' => 'App\\Http\\Controllers\\IncomeController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'incomes.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/incomes/{income}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Account Management,Logistics Specialist',
        ),
        'as' => 'incomes.destroy',
        'uses' => 'App\\Http\\Controllers\\IncomeController@destroy',
        'controller' => 'App\\Http\\Controllers\\IncomeController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::jiwXSXf2ZlYkfu8Y' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categories/search',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist,Customer Service',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@search',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@search',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::jiwXSXf2ZlYkfu8Y',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::t5PFVmAL7jx0PzHn' => 
    array (
      'methods' => 
      array (
        0 => 'PATCH',
      ),
      'uri' => 'api/categories/{id}/quantity',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist,Customer Service',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@changeCategoryQuantityss',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@changeCategoryQuantityss',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::t5PFVmAL7jx0PzHn',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::tvsAIXSJwEvjSW1p' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/productions/{production}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist,Customer Service',
        ),
        'uses' => 'App\\Http\\Controllers\\ProductionController@show',
        'controller' => 'App\\Http\\Controllers\\ProductionController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::tvsAIXSJwEvjSW1p',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::5MjUuBOYEeJV1H6o' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/measurements',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist,Customer Service',
        ),
        'uses' => 'App\\Http\\Controllers\\MeasurementController@index',
        'controller' => 'App\\Http\\Controllers\\MeasurementController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::5MjUuBOYEeJV1H6o',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::vNqAXru4PS98BEDj' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/measurements/{measurement}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist,Customer Service',
        ),
        'uses' => 'App\\Http\\Controllers\\MeasurementController@show',
        'controller' => 'App\\Http\\Controllers\\MeasurementController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::vNqAXru4PS98BEDj',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::A88r6LfTFXj9uhOo' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/allcategories',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@allCategories',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@allCategories',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::A88r6LfTFXj9uhOo',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::IZdtvfAXztNgA8m7' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categories',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@index',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::IZdtvfAXztNgA8m7',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::RKY4ikE5vrlufpt6' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/getCategoryByStockId',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@getCategoryByStockId',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@getCategoryByStockId',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::RKY4ikE5vrlufpt6',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::HJVgINU7GUWskhdo' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/categories',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@store',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::HJVgINU7GUWskhdo',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::tzMeIJQNXjl4VgiM' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/recipes',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeController@index',
        'controller' => 'App\\Http\\Controllers\\RecipeController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::tzMeIJQNXjl4VgiM',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::BXLz1RkFQ2S1IVbn' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/recipes',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeController@store',
        'controller' => 'App\\Http\\Controllers\\RecipeController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::BXLz1RkFQ2S1IVbn',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::IFiyaDnQZ8uNVGg0' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/recipes/bulk-delete',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeController@bulkDestroy',
        'controller' => 'App\\Http\\Controllers\\RecipeController@bulkDestroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::IFiyaDnQZ8uNVGg0',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::NeAGK5shZ7owLNde' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/recipes/import',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeImportController@preview',
        'controller' => 'App\\Http\\Controllers\\RecipeImportController@preview',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::NeAGK5shZ7owLNde',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::2m7ZVQWsiJ2xVLHW' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/recipes/import/confirm',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeImportController@confirm',
        'controller' => 'App\\Http\\Controllers\\RecipeImportController@confirm',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::2m7ZVQWsiJ2xVLHW',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::S5dUmhuWorGOOCeM' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/recipes/import/cancel',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeImportController@cancel',
        'controller' => 'App\\Http\\Controllers\\RecipeImportController@cancel',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::S5dUmhuWorGOOCeM',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::uZQ7LWYbmZpji0Hh' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/recipes/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeController@show',
        'controller' => 'App\\Http\\Controllers\\RecipeController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::uZQ7LWYbmZpji0Hh',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'id' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::xO2sxctqW0DJwtOQ' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/recipes/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeController@update',
        'controller' => 'App\\Http\\Controllers\\RecipeController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::xO2sxctqW0DJwtOQ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'id' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ClOlJdssH4cNUPTS' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/recipes/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeController@destroy',
        'controller' => 'App\\Http\\Controllers\\RecipeController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ClOlJdssH4cNUPTS',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'id' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::u7f40VvGgAEAKYjU' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/recipes/{id}/check-stock',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeController@checkStock',
        'controller' => 'App\\Http\\Controllers\\RecipeController@checkStock',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::u7f40VvGgAEAKYjU',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'id' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::j3rfG7EUt042687a' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/recipes/{id}/execute',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeController@execute',
        'controller' => 'App\\Http\\Controllers\\RecipeController@execute',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::j3rfG7EUt042687a',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'id' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::potjrADmkKMeo18z' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/recipes/{id}/movements',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeController@movements',
        'controller' => 'App\\Http\\Controllers\\RecipeController@movements',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::potjrADmkKMeo18z',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'id' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::BXAzVPpYtLQkFczk' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/recipes/{recipeId}/extra-costs',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeExtraCostController@index',
        'controller' => 'App\\Http\\Controllers\\RecipeExtraCostController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::BXAzVPpYtLQkFczk',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'recipeId' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::eSCAYI6f77LSX4D7' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/recipes/{recipeId}/extra-costs',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeExtraCostController@store',
        'controller' => 'App\\Http\\Controllers\\RecipeExtraCostController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::eSCAYI6f77LSX4D7',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'recipeId' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Py8CmHBHz7lUy0F4' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/recipes/{recipeId}/extra-costs/{extraCostId}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeExtraCostController@update',
        'controller' => 'App\\Http\\Controllers\\RecipeExtraCostController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Py8CmHBHz7lUy0F4',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'recipeId' => '[0-9]+',
        'extraCostId' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::lpyYCfiaYSGu5kHI' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/recipes/{recipeId}/extra-costs/{extraCostId}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeExtraCostController@destroy',
        'controller' => 'App\\Http\\Controllers\\RecipeExtraCostController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::lpyYCfiaYSGu5kHI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'recipeId' => '[0-9]+',
        'extraCostId' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::QtXQqZqHdNn9lAtg' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/recipes/{recipeId}/breakdown',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\RecipeExtraCostController@breakdown',
        'controller' => 'App\\Http\\Controllers\\RecipeExtraCostController@breakdown',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::QtXQqZqHdNn9lAtg',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'recipeId' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::u0RGVqQViUXD5P5q' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/categories/{id}/revision-roll-forward',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ItemRecipeRevisionController@rollForward',
        'controller' => 'App\\Http\\Controllers\\ItemRecipeRevisionController@rollForward',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::u0RGVqQViUXD5P5q',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::It4XsnHVwI5yJuab' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categories/{id}/revision-lineage',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ItemRecipeRevisionController@lineage',
        'controller' => 'App\\Http\\Controllers\\ItemRecipeRevisionController@lineage',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::It4XsnHVwI5yJuab',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::woQSrNniptk8gBIK' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/category/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@getCategoryById',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@getCategoryById',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::woQSrNniptk8gBIK',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::102Oz2KHawwL2WsT' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/editcategory/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@editCategory',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@editCategory',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::102Oz2KHawwL2WsT',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::tvLU5vVChgpkfbd2' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/deletecategory/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@deleteCategory',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@deleteCategory',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::tvLU5vVChgpkfbd2',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::TWOgmHDC6ppfEw4n' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/categories/{category}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@update',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::TWOgmHDC6ppfEw4n',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::JuYcOH6syvRKSSbt' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/categories/{category}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@destroy',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::JuYcOH6syvRKSSbt',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::iyupLZQxn7aW6WXE' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/categories/{category}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CategoriesController@show',
        'controller' => 'App\\Http\\Controllers\\CategoriesController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::iyupLZQxn7aW6WXE',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::5MfUWa8zCNPHg3h9' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/productions',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ProductionController@store',
        'controller' => 'App\\Http\\Controllers\\ProductionController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::5MfUWa8zCNPHg3h9',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::rdJLZ2HpVhSPOANk' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/productions/{production}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ProductionController@update',
        'controller' => 'App\\Http\\Controllers\\ProductionController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::rdJLZ2HpVhSPOANk',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::9wRXCEeHFQElNu7F' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/productions/{production}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ProductionController@destroy',
        'controller' => 'App\\Http\\Controllers\\ProductionController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::9wRXCEeHFQElNu7F',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ZspSULvpmEeFI0Ta' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/measurements',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\MeasurementController@store',
        'controller' => 'App\\Http\\Controllers\\MeasurementController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ZspSULvpmEeFI0Ta',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::MJ2y2pAUZqfozNc5' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/measurements/{measurement}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\MeasurementController@update',
        'controller' => 'App\\Http\\Controllers\\MeasurementController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::MJ2y2pAUZqfozNc5',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::I8J4RZ67JW5ZKTnK' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/measurements/{measurement}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\MeasurementController@destroy',
        'controller' => 'App\\Http\\Controllers\\MeasurementController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::I8J4RZ67JW5ZKTnK',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::kCayXoeA4WqSOr7h' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/allnotification',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\NotificationController@allNotifiy',
        'controller' => 'App\\Http\\Controllers\\NotificationController@allNotifiy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::kCayXoeA4WqSOr7h',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Xbqy2JuRzjJ0Frhf' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/notification/delete/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\NotificationController@destroy',
        'controller' => 'App\\Http\\Controllers\\NotificationController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Xbqy2JuRzjJ0Frhf',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::cZkLCgOqpM8mGdYq' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/manufacture',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ManufactureController@store',
        'controller' => 'App\\Http\\Controllers\\ManufactureController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::cZkLCgOqpM8mGdYq',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Deir5QtSqSMqAC8S' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/manufacture',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ManufactureController@index',
        'controller' => 'App\\Http\\Controllers\\ManufactureController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Deir5QtSqSMqAC8S',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hPQ1zB5Q16k0mUoS' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/manufacture/manfucture_by_warhouse',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ManufactureController@manfucture_by_warhouse',
        'controller' => 'App\\Http\\Controllers\\ManufactureController@manfucture_by_warhouse',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hPQ1zB5Q16k0mUoS',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::UlwiphKI0ZzxTOje' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/manufacture/confirm',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ManufactureController@confirm',
        'controller' => 'App\\Http\\Controllers\\ManufactureController@confirm',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::UlwiphKI0ZzxTOje',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::bQMKWsYnWsWqSBiR' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/manufacture/confirmed',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ManufactureController@confirmed',
        'controller' => 'App\\Http\\Controllers\\ManufactureController@confirmed',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::bQMKWsYnWsWqSBiR',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::qdWkbXXOpHLAEhu1' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/manufacture/done/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ManufactureController@done',
        'controller' => 'App\\Http\\Controllers\\ManufactureController@done',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::qdWkbXXOpHLAEhu1',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::cnC9n8MsiTT9rEra' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/manufacturing/production-orders',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\Manufacturing\\ProductionOrderController@index',
        'controller' => 'App\\Http\\Controllers\\Manufacturing\\ProductionOrderController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::cnC9n8MsiTT9rEra',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::WnJNYjzohEyn7rOJ' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/manufacturing/production-orders',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\Manufacturing\\ProductionOrderController@store',
        'controller' => 'App\\Http\\Controllers\\Manufacturing\\ProductionOrderController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::WnJNYjzohEyn7rOJ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::x7ceK9oDVJ7k0uzl' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/manufacturing/production-orders/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\Manufacturing\\ProductionOrderController@show',
        'controller' => 'App\\Http\\Controllers\\Manufacturing\\ProductionOrderController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::x7ceK9oDVJ7k0uzl',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'id' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Gmjeh4cDoNUpPYgi' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/manufacturing/production-orders/{id}/start',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\Manufacturing\\ProductionOrderController@start',
        'controller' => 'App\\Http\\Controllers\\Manufacturing\\ProductionOrderController@start',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Gmjeh4cDoNUpPYgi',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'id' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Km7M9lHxk1xy2EpH' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/manufacturing/production-orders/{id}/complete',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\Manufacturing\\ProductionOrderController@complete',
        'controller' => 'App\\Http\\Controllers\\Manufacturing\\ProductionOrderController@complete',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Km7M9lHxk1xy2EpH',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'id' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::RUuBqLNTm4THMCzD' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/manufacturing/production-orders/{id}/cancel',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\Manufacturing\\ProductionOrderController@cancel',
        'controller' => 'App\\Http\\Controllers\\Manufacturing\\ProductionOrderController@cancel',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::RUuBqLNTm4THMCzD',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'id' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::9qDTeI9HnFPBTjmt' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/pendingBanks',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\PendingBankBalanceController@pendingBanks',
        'controller' => 'App\\Http\\Controllers\\PendingBankBalanceController@pendingBanks',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::9qDTeI9HnFPBTjmt',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::NSFhxEaSPTFrW9TI' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/pendingBanks',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\PendingBankBalanceController@pendingBanksStatus',
        'controller' => 'App\\Http\\Controllers\\PendingBankBalanceController@pendingBanksStatus',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::NSFhxEaSPTFrW9TI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::0eyrgiYwGf3c2IDX' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/users',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\UserController@index',
        'controller' => 'App\\Http\\Controllers\\UserController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::0eyrgiYwGf3c2IDX',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Qqp85lWDZ5pbWmXu' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/user/delete/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthController@destroy',
        'controller' => 'App\\Http\\Controllers\\AuthController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Qqp85lWDZ5pbWmXu',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::5JFYDhI0nnC2uhJo' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/register',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthController@register',
        'controller' => 'App\\Http\\Controllers\\AuthController@register',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::5JFYDhI0nnC2uhJo',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'approvals.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/approvals',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'as' => 'approvals.index',
        'uses' => 'App\\Http\\Controllers\\ApprovalsController@index',
        'controller' => 'App\\Http\\Controllers\\ApprovalsController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'approvals.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/approvals',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'as' => 'approvals.store',
        'uses' => 'App\\Http\\Controllers\\ApprovalsController@store',
        'controller' => 'App\\Http\\Controllers\\ApprovalsController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'approvals.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/approvals/{approval}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'as' => 'approvals.show',
        'uses' => 'App\\Http\\Controllers\\ApprovalsController@show',
        'controller' => 'App\\Http\\Controllers\\ApprovalsController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'approvals.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/approvals/{approval}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'as' => 'approvals.update',
        'uses' => 'App\\Http\\Controllers\\ApprovalsController@update',
        'controller' => 'App\\Http\\Controllers\\ApprovalsController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'approvals.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/approvals/{approval}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'as' => 'approvals.destroy',
        'uses' => 'App\\Http\\Controllers\\ApprovalsController@destroy',
        'controller' => 'App\\Http\\Controllers\\ApprovalsController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::bnBLo7ZNkCQDmIEY' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/order_source',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\OrderSourceController@store',
        'controller' => 'App\\Http\\Controllers\\OrderSourceController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::bnBLo7ZNkCQDmIEY',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::12pIcB72ne4sNTNm' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/revieworder',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@revieworder',
        'controller' => 'App\\Http\\Controllers\\OrdersController@revieworder',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::12pIcB72ne4sNTNm',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::J5k9Fuhfh1QMFWwP' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/readtempreview/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@readTempReviewOrder',
        'controller' => 'App\\Http\\Controllers\\OrdersController@readTempReviewOrder',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::J5k9Fuhfh1QMFWwP',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::VWHduG1zcMn3fOfL' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shippinglines/{shippingline}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingLineController@show',
        'controller' => 'App\\Http\\Controllers\\ShippingLineController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::VWHduG1zcMn3fOfL',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::GqXwqs0hkhjPmdoQ' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/shippinglines',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingLineController@store',
        'controller' => 'App\\Http\\Controllers\\ShippingLineController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::GqXwqs0hkhjPmdoQ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::n1cOrpBpcbvhgHNq' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/shippinglines/{shippingline}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingLineController@update',
        'controller' => 'App\\Http\\Controllers\\ShippingLineController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::n1cOrpBpcbvhgHNq',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::sf1EeP8vIGSGu9gH' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/shippinglines/{shippingline}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingLineController@destroy',
        'controller' => 'App\\Http\\Controllers\\ShippingLineController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::sf1EeP8vIGSGu9gH',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::cJsK5v4Cs8faHzPK' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/tracking',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@getTrackings',
        'controller' => 'App\\Http\\Controllers\\OrdersController@getTrackings',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::cJsK5v4Cs8faHzPK',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::xdAN7JVwAMyYC6wt' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/tracking/undo',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@undo',
        'controller' => 'App\\Http\\Controllers\\OrdersController@undo',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::xdAN7JVwAMyYC6wt',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Shvv72ON3zS9NHra' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/getActions',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@getActions',
        'controller' => 'App\\Http\\Controllers\\OrdersController@getActions',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Shvv72ON3zS9NHra',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'stock.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/stocks',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'as' => 'stock.index',
        'uses' => 'App\\Http\\Controllers\\V2\\stock\\stockController@index',
        'controller' => 'App\\Http\\Controllers\\V2\\stock\\stockController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'stock.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/stocks',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'as' => 'stock.store',
        'uses' => 'App\\Http\\Controllers\\V2\\stock\\stockController@store',
        'controller' => 'App\\Http\\Controllers\\V2\\stock\\stockController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'stock.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/stocks/{stock}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'as' => 'stock.show',
        'uses' => 'App\\Http\\Controllers\\V2\\stock\\stockController@show',
        'controller' => 'App\\Http\\Controllers\\V2\\stock\\stockController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'stock.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/stocks/{stock}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'as' => 'stock.update',
        'uses' => 'App\\Http\\Controllers\\V2\\stock\\stockController@update',
        'controller' => 'App\\Http\\Controllers\\V2\\stock\\stockController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'stock.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/stocks/{stock}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'as' => 'stock.destroy',
        'uses' => 'App\\Http\\Controllers\\V2\\stock\\stockController@destroy',
        'controller' => 'App\\Http\\Controllers\\V2\\stock\\stockController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::dVLLyzsGvS3aacEP' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shopify/status',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ShopifyIntegrationController@status',
        'controller' => 'App\\Http\\Controllers\\ShopifyIntegrationController@status',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::dVLLyzsGvS3aacEP',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::A8AADjgfVAwtdlEP' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/shopify/sync-product-mappings',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ShopifyIntegrationController@syncProductMappings',
        'controller' => 'App\\Http\\Controllers\\ShopifyIntegrationController@syncProductMappings',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::A8AADjgfVAwtdlEP',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::UrC3hQF0IUxdVAtN' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/shopify/sync-orders',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ShopifyIntegrationController@syncOrders',
        'controller' => 'App\\Http\\Controllers\\ShopifyIntegrationController@syncOrders',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::UrC3hQF0IUxdVAtN',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::QF7AcB9w8wBNN8xi' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shopify/product-mappings',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ShopifyIntegrationController@productMappingsIndex',
        'controller' => 'App\\Http\\Controllers\\ShopifyIntegrationController@productMappingsIndex',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::QF7AcB9w8wBNN8xi',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ZWWFnd1LN4NlsZf8' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shopify/integration/orders',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ShopifyShippingIntegrationController@integrationOrders',
        'controller' => 'App\\Http\\Controllers\\ShopifyShippingIntegrationController@integrationOrders',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ZWWFnd1LN4NlsZf8',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::BSnJr86zg70KigOz' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shopify/integration/products',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ShopifyShippingIntegrationController@integrationProducts',
        'controller' => 'App\\Http\\Controllers\\ShopifyShippingIntegrationController@integrationProducts',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::BSnJr86zg70KigOz',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::iTM4uN6AKlZvHvGy' => 
    array (
      'methods' => 
      array (
        0 => 'PATCH',
      ),
      'uri' => 'api/shopify/integration/products/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ShopifyShippingIntegrationController@updateIntegrationProduct',
        'controller' => 'App\\Http\\Controllers\\ShopifyShippingIntegrationController@updateIntegrationProduct',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::iTM4uN6AKlZvHvGy',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::AHNrkqOl3FjpRWKI' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shopify/integration/failed-jobs',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ShopifyShippingIntegrationController@failedJobs',
        'controller' => 'App\\Http\\Controllers\\ShopifyShippingIntegrationController@failedJobs',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::AHNrkqOl3FjpRWKI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::P8mKtwCaMcT1t0yy' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/shiporder/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@ship_order',
        'controller' => 'App\\Http\\Controllers\\OrdersController@ship_order',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::P8mKtwCaMcT1t0yy',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ABGgybFyySao55JM' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/collectorder/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@collect_order',
        'controller' => 'App\\Http\\Controllers\\OrdersController@collect_order',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ABGgybFyySao55JM',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::WZ2EjIH5v6dys5qT' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/collectorder-bulk',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@bulk_collect_orders',
        'controller' => 'App\\Http\\Controllers\\OrdersController@bulk_collect_orders',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::WZ2EjIH5v6dys5qT',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::lhEm9lM97qx4rBjj' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/order/received/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@received',
        'controller' => 'App\\Http\\Controllers\\OrdersController@received',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::lhEm9lM97qx4rBjj',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::3LRTZTAnQk2ikXXp' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employees/search',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeController@search',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@search',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::3LRTZTAnQk2ikXXp',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::wcjhJCm7vmGwnREI' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employeepermonth/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeController@employeePerMonth',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@employeePerMonth',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::wcjhJCm7vmGwnREI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ffC4Nnwcyi9LmXu4' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employeespermonth',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeController@employeesPerMonth',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@employeesPerMonth',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ffC4Nnwcyi9LmXu4',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::77IIzH2hBXktzTSN' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/getEmpDataPerMonth',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeController@getEmpsDataPerMonth',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@getEmpsDataPerMonth',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::77IIzH2hBXktzTSN',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Wb20xyNVBS6gEeST' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/getEmpDataPerMonth/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeController@getEmpDataPerMonth',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@getEmpDataPerMonth',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Wb20xyNVBS6gEeST',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::vaKxzEHcOk91AmEY' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/empHoursPermission',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeController@empHoursPermission',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@empHoursPermission',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::vaKxzEHcOk91AmEY',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::O1xrPkLZUH6LWZ8L' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/empHoursPermissionall',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeController@empHoursPermissionAll',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@empHoursPermissionAll',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::O1xrPkLZUH6LWZ8L',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hLkGoZ8lAMyGryde' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/employees/edit/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeController@edit',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@edit',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hLkGoZ8lAMyGryde',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::pQpWxHdlq7XcsM5P' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employees/absences',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeSubtractionController@employeesAbsences',
        'controller' => 'App\\Http\\Controllers\\EmployeeSubtractionController@employeesAbsences',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::pQpWxHdlq7XcsM5P',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Km635EJqd3YRRa4G' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/employee/absencestatus',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeSubtractionController@absenceStatus',
        'controller' => 'App\\Http\\Controllers\\EmployeeSubtractionController@absenceStatus',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Km635EJqd3YRRa4G',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::c4C7DYJ6Tq2iGFbC' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employees/accountstatment',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeController@accountStatment',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@accountStatment',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::c4C7DYJ6Tq2iGFbC',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::JkNLi7BqABeF40T5' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employees/accountstatment/reviewed/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeController@reviewedStatus',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@reviewedStatus',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::JkNLi7BqABeF40T5',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::wkeNYYGathsCG5CK' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/employees/excelfingerprintdata',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeController@saveExcelFingerPrintData',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@saveExcelFingerPrintData',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::wkeNYYGathsCG5CK',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::rijZLww2HW80s8cG' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/updatefingerprintsheet',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeFingerPrintSheetController@update',
        'controller' => 'App\\Http\\Controllers\\EmployeeFingerPrintSheetController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::rijZLww2HW80s8cG',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::8m1wadhmBjrw4XjN' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/addCheckOut/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeFingerPrintSheetController@addCheckOut',
        'controller' => 'App\\Http\\Controllers\\EmployeeFingerPrintSheetController@addCheckOut',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::8m1wadhmBjrw4XjN',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::wM1lJMwEvPdzJEga' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/editCheckInOrOut/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeFingerPrintSheetController@editCheckInOrOut',
        'controller' => 'App\\Http\\Controllers\\EmployeeFingerPrintSheetController@editCheckInOrOut',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::wM1lJMwEvPdzJEga',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hm87xhuaxrHDboYN' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/changeCheckIn/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeFingerPrintSheetController@changeCheckIn',
        'controller' => 'App\\Http\\Controllers\\EmployeeFingerPrintSheetController@changeCheckIn',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hm87xhuaxrHDboYN',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::J5sWCZjxv0CrwnZv' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/reviewMonth',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeFingerPrintSheetController@reviewMonth',
        'controller' => 'App\\Http\\Controllers\\EmployeeFingerPrintSheetController@reviewMonth',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::J5sWCZjxv0CrwnZv',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::1tnsYKIktgruZciZ' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/absencededuction',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeFingerPrintSheetController@absenceDeduction',
        'controller' => 'App\\Http\\Controllers\\EmployeeFingerPrintSheetController@absenceDeduction',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::1tnsYKIktgruZciZ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::CRrWdjlr3TTG0Wq6' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/addFixedChangedSalary',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeMeritsController@addFixedChangedSalary',
        'controller' => 'App\\Http\\Controllers\\EmployeeMeritsController@addFixedChangedSalary',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::CRrWdjlr3TTG0Wq6',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employees.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employees',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employees.index',
        'uses' => 'App\\Http\\Controllers\\EmployeeController@index',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employees.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/employees',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employees.store',
        'uses' => 'App\\Http\\Controllers\\EmployeeController@store',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employees.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employees/{employee}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employees.show',
        'uses' => 'App\\Http\\Controllers\\EmployeeController@show',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employees.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/employees/{employee}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employees.update',
        'uses' => 'App\\Http\\Controllers\\EmployeeController@update',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employees.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/employees/{employee}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employees.destroy',
        'uses' => 'App\\Http\\Controllers\\EmployeeController@destroy',
        'controller' => 'App\\Http\\Controllers\\EmployeeController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeemerit.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employeemerit',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeemerit.index',
        'uses' => 'App\\Http\\Controllers\\EmployeeMeritsController@index',
        'controller' => 'App\\Http\\Controllers\\EmployeeMeritsController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeemerit.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/employeemerit',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeemerit.store',
        'uses' => 'App\\Http\\Controllers\\EmployeeMeritsController@store',
        'controller' => 'App\\Http\\Controllers\\EmployeeMeritsController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeemerit.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employeemerit/{employeemerit}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeemerit.show',
        'uses' => 'App\\Http\\Controllers\\EmployeeMeritsController@show',
        'controller' => 'App\\Http\\Controllers\\EmployeeMeritsController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeemerit.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/employeemerit/{employeemerit}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeemerit.update',
        'uses' => 'App\\Http\\Controllers\\EmployeeMeritsController@update',
        'controller' => 'App\\Http\\Controllers\\EmployeeMeritsController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeemerit.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/employeemerit/{employeemerit}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeemerit.destroy',
        'uses' => 'App\\Http\\Controllers\\EmployeeMeritsController@destroy',
        'controller' => 'App\\Http\\Controllers\\EmployeeMeritsController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeesubtraction.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employeesubtraction',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeesubtraction.index',
        'uses' => 'App\\Http\\Controllers\\EmployeeSubtractionController@index',
        'controller' => 'App\\Http\\Controllers\\EmployeeSubtractionController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeesubtraction.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/employeesubtraction',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeesubtraction.store',
        'uses' => 'App\\Http\\Controllers\\EmployeeSubtractionController@store',
        'controller' => 'App\\Http\\Controllers\\EmployeeSubtractionController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeesubtraction.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employeesubtraction/{employeesubtraction}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeesubtraction.show',
        'uses' => 'App\\Http\\Controllers\\EmployeeSubtractionController@show',
        'controller' => 'App\\Http\\Controllers\\EmployeeSubtractionController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeesubtraction.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/employeesubtraction/{employeesubtraction}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeesubtraction.update',
        'uses' => 'App\\Http\\Controllers\\EmployeeSubtractionController@update',
        'controller' => 'App\\Http\\Controllers\\EmployeeSubtractionController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeesubtraction.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/employeesubtraction/{employeesubtraction}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeesubtraction.destroy',
        'uses' => 'App\\Http\\Controllers\\EmployeeSubtractionController@destroy',
        'controller' => 'App\\Http\\Controllers\\EmployeeSubtractionController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeeadvancepayment.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employeeadvancepayment',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeeadvancepayment.index',
        'uses' => 'App\\Http\\Controllers\\EmployeeAdvancePaymentController@index',
        'controller' => 'App\\Http\\Controllers\\EmployeeAdvancePaymentController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeeadvancepayment.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/employeeadvancepayment',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeeadvancepayment.store',
        'uses' => 'App\\Http\\Controllers\\EmployeeAdvancePaymentController@store',
        'controller' => 'App\\Http\\Controllers\\EmployeeAdvancePaymentController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeeadvancepayment.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employeeadvancepayment/{employeeadvancepayment}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeeadvancepayment.show',
        'uses' => 'App\\Http\\Controllers\\EmployeeAdvancePaymentController@show',
        'controller' => 'App\\Http\\Controllers\\EmployeeAdvancePaymentController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeeadvancepayment.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/employeeadvancepayment/{employeeadvancepayment}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeeadvancepayment.update',
        'uses' => 'App\\Http\\Controllers\\EmployeeAdvancePaymentController@update',
        'controller' => 'App\\Http\\Controllers\\EmployeeAdvancePaymentController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeeadvancepayment.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/employeeadvancepayment/{employeeadvancepayment}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeeadvancepayment.destroy',
        'uses' => 'App\\Http\\Controllers\\EmployeeAdvancePaymentController@destroy',
        'controller' => 'App\\Http\\Controllers\\EmployeeAdvancePaymentController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::EQN711zqAsmL303M' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/employeemonthpaid/bulk',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\EmployeeMonthPaidController@bulkStore',
        'controller' => 'App\\Http\\Controllers\\EmployeeMonthPaidController@bulkStore',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::EQN711zqAsmL303M',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeemonthpaid.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employeemonthpaid',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeemonthpaid.index',
        'uses' => 'App\\Http\\Controllers\\EmployeeMonthPaidController@index',
        'controller' => 'App\\Http\\Controllers\\EmployeeMonthPaidController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeemonthpaid.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/employeemonthpaid',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeemonthpaid.store',
        'uses' => 'App\\Http\\Controllers\\EmployeeMonthPaidController@store',
        'controller' => 'App\\Http\\Controllers\\EmployeeMonthPaidController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeemonthpaid.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/employeemonthpaid/{employeemonthpaid}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeemonthpaid.show',
        'uses' => 'App\\Http\\Controllers\\EmployeeMonthPaidController@show',
        'controller' => 'App\\Http\\Controllers\\EmployeeMonthPaidController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeemonthpaid.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/employeemonthpaid/{employeemonthpaid}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeemonthpaid.update',
        'uses' => 'App\\Http\\Controllers\\EmployeeMonthPaidController@update',
        'controller' => 'App\\Http\\Controllers\\EmployeeMonthPaidController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employeemonthpaid.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/employeemonthpaid/{employeemonthpaid}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'as' => 'employeemonthpaid.destroy',
        'uses' => 'App\\Http\\Controllers\\EmployeeMonthPaidController@destroy',
        'controller' => 'App\\Http\\Controllers\\EmployeeMonthPaidController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::XzIVRxUJbPAPFAnm' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/partcollectorder/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@partCollect_order',
        'controller' => 'App\\Http\\Controllers\\OrdersController@partCollect_order',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::XzIVRxUJbPAPFAnm',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::60Wtm2zJKDGc5yt3' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/addshippmentnumber/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@addShippmentNumber',
        'controller' => 'App\\Http\\Controllers\\OrdersController@addShippmentNumber',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::60Wtm2zJKDGc5yt3',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::rCluNSm7UGWLi71W' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/googlesheet/{sheet}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'uses' => 'App\\Http\\Controllers\\GoogleController@addData',
        'controller' => 'App\\Http\\Controllers\\GoogleController@addData',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::rCluNSm7UGWLi71W',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hxLpBLqv8jDLDmnz' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/edit-lead',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadController@edit',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadController@edit',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hxLpBLqv8jDLDmnz',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::xsIZrKImlYi5NMK5' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead/team-users',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadController@getLeadTeamUsers',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadController@getLeadTeamUsers',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::xsIZrKImlYi5NMK5',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::GKKNVmrjaod60xV1' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead/activity-stats',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadController@getLeadActivityStats',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadController@getLeadActivityStats',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::GKKNVmrjaod60xV1',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::frdsABCqu0ymcvyF' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead/recommenders/pending',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadController@getPendingRecommenders',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadController@getPendingRecommenders',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::frdsABCqu0ymcvyF',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::reLjefXDBomKWegU' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/lead/recommenders/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadController@deleteRecommender',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadController@deleteRecommender',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::reLjefXDBomKWegU',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Q1Q5FKuGoDKPAfCW' => 
    array (
      'methods' => 
      array (
        0 => 'PATCH',
      ),
      'uri' => 'api/lead/recommenders/{id}/toggle-done',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadController@toggleRecommenderDone',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadController@toggleRecommenderDone',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Q1Q5FKuGoDKPAfCW',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead.index',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadController@index',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/lead',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead.store',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadController@store',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead/{lead}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead.show',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadController@show',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/lead/{lead}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead.update',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadController@update',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/lead/{lead}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead.destroy',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadController@destroy',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-source.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead-source',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-source.index',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadSourceController@index',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadSourceController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-source.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/lead-source',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-source.store',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadSourceController@store',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadSourceController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-source.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead-source/{lead_source}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-source.show',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadSourceController@show',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadSourceController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-source.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/lead-source/{lead_source}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-source.update',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadSourceController@update',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadSourceController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-source.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/lead-source/{lead_source}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-source.destroy',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadSourceController@destroy',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadSourceController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-tool.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead-tool',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-tool.index',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadToolController@index',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadToolController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-tool.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/lead-tool',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-tool.store',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadToolController@store',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadToolController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-tool.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead-tool/{lead_tool}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-tool.show',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadToolController@show',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadToolController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-tool.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/lead-tool/{lead_tool}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-tool.update',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadToolController@update',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadToolController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-tool.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/lead-tool/{lead_tool}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-tool.destroy',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesLeadToolController@destroy',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesLeadToolController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-industry.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead-industry',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-industry.index',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesIndustryController@index',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesIndustryController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-industry.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/lead-industry',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-industry.store',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesIndustryController@store',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesIndustryController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-industry.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead-industry/{lead_industry}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-industry.show',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesIndustryController@show',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesIndustryController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-industry.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/lead-industry/{lead_industry}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-industry.update',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesIndustryController@update',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesIndustryController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-industry.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/lead-industry/{lead_industry}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-industry.destroy',
        'uses' => 'App\\Http\\Controllers\\CorporateSalesIndustryController@destroy',
        'controller' => 'App\\Http\\Controllers\\CorporateSalesIndustryController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-statuses.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead-statuses',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-statuses.index',
        'uses' => 'App\\Http\\Controllers\\LeadStatusController@index',
        'controller' => 'App\\Http\\Controllers\\LeadStatusController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-statuses.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/lead-statuses',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-statuses.store',
        'uses' => 'App\\Http\\Controllers\\LeadStatusController@store',
        'controller' => 'App\\Http\\Controllers\\LeadStatusController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-statuses.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead-statuses/{lead_status}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-statuses.show',
        'uses' => 'App\\Http\\Controllers\\LeadStatusController@show',
        'controller' => 'App\\Http\\Controllers\\LeadStatusController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-statuses.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/lead-statuses/{lead_status}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-statuses.update',
        'uses' => 'App\\Http\\Controllers\\LeadStatusController@update',
        'controller' => 'App\\Http\\Controllers\\LeadStatusController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'lead-statuses.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/lead-statuses/{lead_status}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'as' => 'lead-statuses.destroy',
        'uses' => 'App\\Http\\Controllers\\LeadStatusController@destroy',
        'controller' => 'App\\Http\\Controllers\\LeadStatusController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::BhaDyqgYB35VCLLq' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead-statuses/active',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'uses' => 'App\\Http\\Controllers\\LeadStatusController@getActive',
        'controller' => 'App\\Http\\Controllers\\LeadStatusController@getActive',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::BhaDyqgYB35VCLLq',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::KJouU4KCDPWzYtv5' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead-statuses/closed-won',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'uses' => 'App\\Http\\Controllers\\LeadStatusController@getClosedWon',
        'controller' => 'App\\Http\\Controllers\\LeadStatusController@getClosedWon',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::KJouU4KCDPWzYtv5',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::pGorOCWthh7tor0z' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead-statuses/closed-lost',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'uses' => 'App\\Http\\Controllers\\LeadStatusController@getClosedLost',
        'controller' => 'App\\Http\\Controllers\\LeadStatusController@getClosedLost',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::pGorOCWthh7tor0z',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::2dpMvMPcvbSjI8fX' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/lead-statuses/follow-up-leads',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'uses' => 'App\\Http\\Controllers\\LeadStatusController@getFollowUpLeads',
        'controller' => 'App\\Http\\Controllers\\LeadStatusController@getFollowUpLeads',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::2dpMvMPcvbSjI8fX',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::gYOQzSdfCdanlJDi' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/lead-statuses/lead/{lead}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Shipping Management,Corparates',
        ),
        'uses' => 'App\\Http\\Controllers\\LeadStatusController@updateLeadStatus',
        'controller' => 'App\\Http\\Controllers\\LeadStatusController@updateLeadStatus',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::gYOQzSdfCdanlJDi',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::JnDmyyJ56pgLNbns' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/orders',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@store',
        'controller' => 'App\\Http\\Controllers\\OrdersController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::JnDmyyJ56pgLNbns',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::odSrOrFOkeUBfXHV' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/phonenumbers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@phoneNumbers',
        'controller' => 'App\\Http\\Controllers\\OrdersController@phoneNumbers',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::odSrOrFOkeUBfXHV',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::0eFYCokO6xwPskyy' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/shipping_methods',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingMethodsController@store',
        'controller' => 'App\\Http\\Controllers\\ShippingMethodsController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::0eFYCokO6xwPskyy',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::IgVt48OavAFxzdMw' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/companies',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Account Management,Logistics Specialist,Data Entry',
        ),
        'uses' => 'App\\Http\\Controllers\\CustomerCompanyController@store',
        'controller' => 'App\\Http\\Controllers\\CustomerCompanyController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::IgVt48OavAFxzdMw',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::oJD6HFyFq6rlVHeh' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/companies',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Account Management,Logistics Specialist,Data Entry',
        ),
        'uses' => 'App\\Http\\Controllers\\CustomerCompanyController@index',
        'controller' => 'App\\Http\\Controllers\\CustomerCompanyController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::oJD6HFyFq6rlVHeh',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::6oyxmwIk1HiHMtCH' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/getOrdersNumbers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Account Management,Logistics Specialist,Data Entry',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@getOrdersNumbers',
        'controller' => 'App\\Http\\Controllers\\OrdersController@getOrdersNumbers',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::6oyxmwIk1HiHMtCH',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'ShippingLineStatement.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/ShippingLineStatement',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Account Management,Logistics Specialist,Data Entry',
        ),
        'as' => 'ShippingLineStatement.index',
        'uses' => 'App\\Http\\Controllers\\ShippingLineStatementController@index',
        'controller' => 'App\\Http\\Controllers\\ShippingLineStatementController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'ShippingLineStatement.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/ShippingLineStatement',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Account Management,Logistics Specialist,Data Entry',
        ),
        'as' => 'ShippingLineStatement.store',
        'uses' => 'App\\Http\\Controllers\\ShippingLineStatementController@store',
        'controller' => 'App\\Http\\Controllers\\ShippingLineStatementController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'ShippingLineStatement.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/ShippingLineStatement/{ShippingLineStatement}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Account Management,Logistics Specialist,Data Entry',
        ),
        'as' => 'ShippingLineStatement.show',
        'uses' => 'App\\Http\\Controllers\\ShippingLineStatementController@show',
        'controller' => 'App\\Http\\Controllers\\ShippingLineStatementController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'ShippingLineStatement.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/ShippingLineStatement/{ShippingLineStatement}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Account Management,Logistics Specialist,Data Entry',
        ),
        'as' => 'ShippingLineStatement.update',
        'uses' => 'App\\Http\\Controllers\\ShippingLineStatementController@update',
        'controller' => 'App\\Http\\Controllers\\ShippingLineStatementController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'ShippingLineStatement.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/ShippingLineStatement/{ShippingLineStatement}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Account Management,Logistics Specialist,Data Entry',
        ),
        'as' => 'ShippingLineStatement.destroy',
        'uses' => 'App\\Http\\Controllers\\ShippingLineStatementController@destroy',
        'controller' => 'App\\Http\\Controllers\\ShippingLineStatementController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::2VDurLip6ht7kmOk' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/companies/search',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CustomerCompanyController@search',
        'controller' => 'App\\Http\\Controllers\\CustomerCompanyController@search',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::2VDurLip6ht7kmOk',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::MC28ycnP1oqzIfiK' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/companies/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CustomerCompanyController@customerCompanyBalance',
        'controller' => 'App\\Http\\Controllers\\CustomerCompanyController@customerCompanyBalance',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::MC28ycnP1oqzIfiK',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::XiPVBf108p5FS3J9' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/companies/companycollect/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\CustomerCompanyController@companyCollect',
        'controller' => 'App\\Http\\Controllers\\CustomerCompanyController@companyCollect',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::XiPVBf108p5FS3J9',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::bKib4TnB8FYWDQnb' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/editorder/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Shipping Management',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@edit',
        'controller' => 'App\\Http\\Controllers\\OrdersController@edit',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::bKib4TnB8FYWDQnb',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::sUMcYB36KD1A186s' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/confirm/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Shipping Management,Customer Service',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@confirm',
        'controller' => 'App\\Http\\Controllers\\OrdersController@confirm',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::sUMcYB36KD1A186s',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::QBAMMrRhSp9vYoa1' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/refuseorder/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Logistics Specialist,Shipping Management',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@refuseOrder',
        'controller' => 'App\\Http\\Controllers\\OrdersController@refuseOrder',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::QBAMMrRhSp9vYoa1',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::kKqNRNtxEci5S3Si' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/order/maintained/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Logistics Specialist,Shipping Management',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@maintained',
        'controller' => 'App\\Http\\Controllers\\OrdersController@maintained',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::kKqNRNtxEci5S3Si',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::HqzqeAgZw8xCZNXz' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shippingcompany/search',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingCompanyController@search',
        'controller' => 'App\\Http\\Controllers\\ShippingCompanyController@search',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::HqzqeAgZw8xCZNXz',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::pEmLGJcl1czzHFzx' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shippingcompanies',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingCompanyController@index',
        'controller' => 'App\\Http\\Controllers\\ShippingCompanyController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::pEmLGJcl1czzHFzx',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::MTWH75nWjuOiIQXR' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/shippingcompanies',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingCompanyController@store',
        'controller' => 'App\\Http\\Controllers\\ShippingCompanyController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::MTWH75nWjuOiIQXR',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::1tI5lFXBzGHvXShi' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shippingcompanies/{shippingcompany}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingCompanyController@show',
        'controller' => 'App\\Http\\Controllers\\ShippingCompanyController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::1tI5lFXBzGHvXShi',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::0AMm7Ra0PfjXTYdl' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/shippingcompanies/{shippingcompany}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingCompanyController@update',
        'controller' => 'App\\Http\\Controllers\\ShippingCompanyController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::0AMm7Ra0PfjXTYdl',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::bUzuFyDbEsF7mef8' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/shippingcompanies/{shippingcompany}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingCompanyController@destroy',
        'controller' => 'App\\Http\\Controllers\\ShippingCompanyController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::bUzuFyDbEsF7mef8',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::wBZI0rgF6OBBRVif' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shippingcompany/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Account Management,Logistics Specialist',
        ),
        'uses' => 'App\\Http\\Controllers\\ShippingCompanyController@show',
        'controller' => 'App\\Http\\Controllers\\ShippingCompanyController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::wBZI0rgF6OBBRVif',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::IZ9kMjselTFzgh4D' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/changestatus/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Logistics Specialist,Shipping Management,Data Entry',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@change_status',
        'controller' => 'App\\Http\\Controllers\\OrdersController@change_status',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::IZ9kMjselTFzgh4D',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::aU1ieTANgOFFEsxO' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/vip/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Shipping Management,Customer Service',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@vip',
        'controller' => 'App\\Http\\Controllers\\OrdersController@vip',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::aU1ieTANgOFFEsxO',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::8aNRRVdBxM0cwFLF' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/shortage/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Shipping Management,Customer Service',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@shortage',
        'controller' => 'App\\Http\\Controllers\\OrdersController@shortage',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::8aNRRVdBxM0cwFLF',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'offer.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/offer',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Shipping Management,Customer Service,Corparates',
        ),
        'as' => 'offer.index',
        'uses' => 'App\\Http\\Controllers\\OffersController@index',
        'controller' => 'App\\Http\\Controllers\\OffersController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'offer.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/offer',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Shipping Management,Customer Service,Corparates',
        ),
        'as' => 'offer.store',
        'uses' => 'App\\Http\\Controllers\\OffersController@store',
        'controller' => 'App\\Http\\Controllers\\OffersController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'offer.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/offer/{offer}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Shipping Management,Customer Service,Corparates',
        ),
        'as' => 'offer.show',
        'uses' => 'App\\Http\\Controllers\\OffersController@show',
        'controller' => 'App\\Http\\Controllers\\OffersController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'offer.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/offer/{offer}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Shipping Management,Customer Service,Corparates',
        ),
        'as' => 'offer.update',
        'uses' => 'App\\Http\\Controllers\\OffersController@update',
        'controller' => 'App\\Http\\Controllers\\OffersController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'offer.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/offer/{offer}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Shipping Management,Customer Service,Corparates',
        ),
        'as' => 'offer.destroy',
        'uses' => 'App\\Http\\Controllers\\OffersController@destroy',
        'controller' => 'App\\Http\\Controllers\\OffersController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::SvPT0Fm9duqZ6uT7' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/userrevieworder',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Review Management',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@userReviewOrder',
        'controller' => 'App\\Http\\Controllers\\OrdersController@userReviewOrder',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::SvPT0Fm9duqZ6uT7',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::WBn43S28LthYhhtj' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/tempreview/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Data Entry,Review Management',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@userTempReviewOrder',
        'controller' => 'App\\Http\\Controllers\\OrdersController@userTempReviewOrder',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::WBn43S28LthYhhtj',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::PkRCM4VFXXaV9vnm' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/addnote/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
          2 => 'department.access:Admin,Operation Management,Operation Specialist,Shipping Management,Data Entry,Account Management,Logistics Specialist,Customer Service',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@addNote',
        'controller' => 'App\\Http\\Controllers\\OrdersController@addNote',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::PkRCM4VFXXaV9vnm',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::SqAVT4R3VdooDLie' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/transactions/by-customer-order/search',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\OrdersController@allUserUnique',
        'controller' => 'App\\Http\\Controllers\\OrdersController@allUserUnique',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::SqAVT4R3VdooDLie',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::6x3STkW7kXwmXTJ3' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/transactions/by-customer-order/detailed',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Transaction\\TransactionController@index',
        'controller' => 'App\\Http\\Controllers\\V2\\Transaction\\TransactionController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::6x3STkW7kXwmXTJ3',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::HBjxeeGBJmybDBeQ' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/tree_accounts/{id}/balance-adjustment',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@balanceAdjustment',
        'controller' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@balanceAdjustment',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::HBjxeeGBJmybDBeQ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ZG9XlFH2Y690yZTG' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/tree_accounts/bulk-balance-adjustment',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@bulkBalanceAdjustment',
        'controller' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@bulkBalanceAdjustment',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ZG9XlFH2Y690yZTG',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'tree_account.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/tree_accounts',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'as' => 'tree_account.index',
        'uses' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@index',
        'controller' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'tree_account.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/tree_accounts',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'as' => 'tree_account.store',
        'uses' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@store',
        'controller' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'tree_account.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/tree_accounts/{tree_account}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'as' => 'tree_account.show',
        'uses' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@show',
        'controller' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'tree_account.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/tree_accounts/{tree_account}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'as' => 'tree_account.update',
        'uses' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@update',
        'controller' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'tree_account.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/tree_accounts/{tree_account}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'as' => 'tree_account.destroy',
        'uses' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@destroy',
        'controller' => 'App\\Http\\Controllers\\V2\\TreeAccount\\TreeAccountController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::OgkgNd3ktsqFC15K' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/vouchers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\VoucherController@index',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\VoucherController@index',
        'namespace' => NULL,
        'prefix' => 'api/accounting/vouchers',
        'where' => 
        array (
        ),
        'as' => 'generated::OgkgNd3ktsqFC15K',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::WHXAqzUc6L2mtOec' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/vouchers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\VoucherController@store',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\VoucherController@store',
        'namespace' => NULL,
        'prefix' => 'api/accounting/vouchers',
        'where' => 
        array (
        ),
        'as' => 'generated::WHXAqzUc6L2mtOec',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::gbs7sA9lXqk93ewU' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/vouchers/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\VoucherController@show',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\VoucherController@show',
        'namespace' => NULL,
        'prefix' => 'api/accounting/vouchers',
        'where' => 
        array (
        ),
        'as' => 'generated::gbs7sA9lXqk93ewU',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hkZe7i1qsgiDVYdg' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/accounting/vouchers/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\VoucherController@update',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\VoucherController@update',
        'namespace' => NULL,
        'prefix' => 'api/accounting/vouchers',
        'where' => 
        array (
        ),
        'as' => 'generated::hkZe7i1qsgiDVYdg',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::29AZj80EH3ePM76A' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/accounting/vouchers/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\VoucherController@destroy',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\VoucherController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/accounting/vouchers',
        'where' => 
        array (
        ),
        'as' => 'generated::29AZj80EH3ePM76A',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::dflMef0e5dhAfPXv' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/cost-centers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\CostCenterController@index',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\CostCenterController@index',
        'namespace' => NULL,
        'prefix' => 'api/accounting/cost-centers',
        'where' => 
        array (
        ),
        'as' => 'generated::dflMef0e5dhAfPXv',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::VzAOwKtRDH9cx8dj' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/cost-centers/tree',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\CostCenterController@tree',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\CostCenterController@tree',
        'namespace' => NULL,
        'prefix' => 'api/accounting/cost-centers',
        'where' => 
        array (
        ),
        'as' => 'generated::VzAOwKtRDH9cx8dj',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::WBOzKaUK8oupEepj' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/cost-centers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\CostCenterController@store',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\CostCenterController@store',
        'namespace' => NULL,
        'prefix' => 'api/accounting/cost-centers',
        'where' => 
        array (
        ),
        'as' => 'generated::WBOzKaUK8oupEepj',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::4NCQdYx9Cfiu48JW' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/cost-centers/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\CostCenterController@show',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\CostCenterController@show',
        'namespace' => NULL,
        'prefix' => 'api/accounting/cost-centers',
        'where' => 
        array (
        ),
        'as' => 'generated::4NCQdYx9Cfiu48JW',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::BmhzphlMkVX8OlOQ' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/accounting/cost-centers/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\CostCenterController@update',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\CostCenterController@update',
        'namespace' => NULL,
        'prefix' => 'api/accounting/cost-centers',
        'where' => 
        array (
        ),
        'as' => 'generated::BmhzphlMkVX8OlOQ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::tHQcJFjLgv32cJNG' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/accounting/cost-centers/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\CostCenterController@destroy',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\CostCenterController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/accounting/cost-centers',
        'where' => 
        array (
        ),
        'as' => 'generated::tHQcJFjLgv32cJNG',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::AMsICZZ3HIQ32EWP' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/safes',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@index',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@index',
        'namespace' => NULL,
        'prefix' => 'api/accounting/safes',
        'where' => 
        array (
        ),
        'as' => 'generated::AMsICZZ3HIQ32EWP',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::mt4RvrfKEnboUO2H' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/safes',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@store',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@store',
        'namespace' => NULL,
        'prefix' => 'api/accounting/safes',
        'where' => 
        array (
        ),
        'as' => 'generated::mt4RvrfKEnboUO2H',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::XXhQvTPEtG9wAEpz' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/safes/transfer',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@transfer',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@transfer',
        'namespace' => NULL,
        'prefix' => 'api/accounting/safes',
        'where' => 
        array (
        ),
        'as' => 'generated::XXhQvTPEtG9wAEpz',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::AlN8nY5SobUcSLAx' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/safes/direct-transaction',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@directTransaction',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@directTransaction',
        'namespace' => NULL,
        'prefix' => 'api/accounting/safes',
        'where' => 
        array (
        ),
        'as' => 'generated::AlN8nY5SobUcSLAx',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::51lyXdk06RMq43XJ' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/safes/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@show',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@show',
        'namespace' => NULL,
        'prefix' => 'api/accounting/safes',
        'where' => 
        array (
        ),
        'as' => 'generated::51lyXdk06RMq43XJ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::QEPQD2lVMgq86nVs' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/accounting/safes/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@update',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@update',
        'namespace' => NULL,
        'prefix' => 'api/accounting/safes',
        'where' => 
        array (
        ),
        'as' => 'generated::QEPQD2lVMgq86nVs',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::tmpHcxg3BSAi6VR4' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/accounting/safes/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@destroy',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\SafeController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/accounting/safes',
        'where' => 
        array (
        ),
        'as' => 'generated::tmpHcxg3BSAi6VR4',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ar2dVzQv0tCh39DX' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/daily-entries',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\DailyEntryController@index',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\DailyEntryController@index',
        'namespace' => NULL,
        'prefix' => 'api/accounting/daily-entries',
        'where' => 
        array (
        ),
        'as' => 'generated::ar2dVzQv0tCh39DX',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::LtPh2dNawRBymG1y' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/daily-entries',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\DailyEntryController@store',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\DailyEntryController@store',
        'namespace' => NULL,
        'prefix' => 'api/accounting/daily-entries',
        'where' => 
        array (
        ),
        'as' => 'generated::LtPh2dNawRBymG1y',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::boRsaB9g14i75X3P' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/daily-entries/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\DailyEntryController@show',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\DailyEntryController@show',
        'namespace' => NULL,
        'prefix' => 'api/accounting/daily-entries',
        'where' => 
        array (
        ),
        'as' => 'generated::boRsaB9g14i75X3P',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::XwfskbS6cXOOZPQW' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/accounting/daily-entries/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\DailyEntryController@update',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\DailyEntryController@update',
        'namespace' => NULL,
        'prefix' => 'api/accounting/daily-entries',
        'where' => 
        array (
        ),
        'as' => 'generated::XwfskbS6cXOOZPQW',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::aUoJqvLenTkoNt6F' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/accounting/daily-entries/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\DailyEntryController@destroy',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\DailyEntryController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/accounting/daily-entries',
        'where' => 
        array (
        ),
        'as' => 'generated::aUoJqvLenTkoNt6F',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::lhQ6OOr6o39VQv0k' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/banks',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@index',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@index',
        'namespace' => NULL,
        'prefix' => 'api/accounting/banks',
        'where' => 
        array (
        ),
        'as' => 'generated::lhQ6OOr6o39VQv0k',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::L6MR7rJFLiS89ckw' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/banks',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@store',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@store',
        'namespace' => NULL,
        'prefix' => 'api/accounting/banks',
        'where' => 
        array (
        ),
        'as' => 'generated::L6MR7rJFLiS89ckw',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::8hSYCLiqGMIz5NF0' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/banks/transfer',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@transfer',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@transfer',
        'namespace' => NULL,
        'prefix' => 'api/accounting/banks',
        'where' => 
        array (
        ),
        'as' => 'generated::8hSYCLiqGMIz5NF0',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::aUnovgtXWSaKWVW2' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/banks/direct-transaction',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@directTransaction',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@directTransaction',
        'namespace' => NULL,
        'prefix' => 'api/accounting/banks',
        'where' => 
        array (
        ),
        'as' => 'generated::aUnovgtXWSaKWVW2',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::CeROVd8Qx9jqtmb8' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/banks/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@show',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@show',
        'namespace' => NULL,
        'prefix' => 'api/accounting/banks',
        'where' => 
        array (
        ),
        'as' => 'generated::CeROVd8Qx9jqtmb8',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::JGlzpXtii0cz8dUL' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/accounting/banks/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@update',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@update',
        'namespace' => NULL,
        'prefix' => 'api/accounting/banks',
        'where' => 
        array (
        ),
        'as' => 'generated::JGlzpXtii0cz8dUL',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::h4xogp1Ll2Eb8xQm' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/accounting/banks/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@destroy',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\BankController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/accounting/banks',
        'where' => 
        array (
        ),
        'as' => 'generated::h4xogp1Ll2Eb8xQm',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::xpKZlYfkvF7fk8Vf' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/capitals',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\CapitalController@index',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\CapitalController@index',
        'namespace' => NULL,
        'prefix' => 'api/accounting/capitals',
        'where' => 
        array (
        ),
        'as' => 'generated::xpKZlYfkvF7fk8Vf',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hoPOm1EE8HrG7IDX' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/capitals',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\CapitalController@store',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\CapitalController@store',
        'namespace' => NULL,
        'prefix' => 'api/accounting/capitals',
        'where' => 
        array (
        ),
        'as' => 'generated::hoPOm1EE8HrG7IDX',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::zNMr8sCmSaasGBLT' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/payment-sources',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\PaymentSourcesController@index',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\PaymentSourcesController@index',
        'namespace' => NULL,
        'prefix' => 'api/accounting',
        'where' => 
        array (
        ),
        'as' => 'generated::zNMr8sCmSaasGBLT',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::lo9ii8d965vAIbqy' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/reports/daily-ledger',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@dailyLedger',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@dailyLedger',
        'namespace' => NULL,
        'prefix' => 'api/accounting/reports',
        'where' => 
        array (
        ),
        'as' => 'generated::lo9ii8d965vAIbqy',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::5JUoYMB0znFVSXrn' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/reports/account-balance',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@accountBalance',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@accountBalance',
        'namespace' => NULL,
        'prefix' => 'api/accounting/reports',
        'where' => 
        array (
        ),
        'as' => 'generated::5JUoYMB0znFVSXrn',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::AWw0EK4Y6W5GTjmL' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/reports/trial-balance',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@trialBalance',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@trialBalance',
        'namespace' => NULL,
        'prefix' => 'api/accounting/reports',
        'where' => 
        array (
        ),
        'as' => 'generated::AWw0EK4Y6W5GTjmL',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::cV7wBdQcueUc1LIg' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/reports/accounting-tree',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@accountingTree',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@accountingTree',
        'namespace' => NULL,
        'prefix' => 'api/accounting/reports',
        'where' => 
        array (
        ),
        'as' => 'generated::cV7wBdQcueUc1LIg',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::co15p88lBaHMA4G5' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/reports/account-statement',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@accountStatement',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@accountStatement',
        'namespace' => NULL,
        'prefix' => 'api/accounting/reports',
        'where' => 
        array (
        ),
        'as' => 'generated::co15p88lBaHMA4G5',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::jCXhU4cwssjQJ92V' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/reports/account-hierarchy',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@getAccountHierarchy',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@getAccountHierarchy',
        'namespace' => NULL,
        'prefix' => 'api/accounting/reports',
        'where' => 
        array (
        ),
        'as' => 'generated::jCXhU4cwssjQJ92V',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::7xzcSALzp8nJ4BAh' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/reports/validate-income-structure',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@validateIncomeStructure',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@validateIncomeStructure',
        'namespace' => NULL,
        'prefix' => 'api/accounting/reports',
        'where' => 
        array (
        ),
        'as' => 'generated::7xzcSALzp8nJ4BAh',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::6oyIrG0blw8E0bny' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/reports/income-statement',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@incomeStatement',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@incomeStatement',
        'namespace' => NULL,
        'prefix' => 'api/accounting/reports',
        'where' => 
        array (
        ),
        'as' => 'generated::6oyIrG0blw8E0bny',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::FrIq6nIaQ8Lmhccn' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/reports/product-performance',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@productPerformance',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@productPerformance',
        'namespace' => NULL,
        'prefix' => 'api/accounting/reports',
        'where' => 
        array (
        ),
        'as' => 'generated::FrIq6nIaQ8Lmhccn',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::va0IaQ6RZtoNRfNW' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/reports/category-profitability',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@categoryProfitability',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@categoryProfitability',
        'namespace' => NULL,
        'prefix' => 'api/accounting/reports',
        'where' => 
        array (
        ),
        'as' => 'generated::va0IaQ6RZtoNRfNW',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::qjnR0Pq6ekKe4ikm' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/process-cash-transaction',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@processCashTransaction',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@processCashTransaction',
        'namespace' => NULL,
        'prefix' => 'api/accounting',
        'where' => 
        array (
        ),
        'as' => 'generated::qjnR0Pq6ekKe4ikm',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::uJecO9AXjuU8nZps' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/update-hierarchy-balances',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@updateHierarchyBalances',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@updateHierarchyBalances',
        'namespace' => NULL,
        'prefix' => 'api/accounting',
        'where' => 
        array (
        ),
        'as' => 'generated::uJecO9AXjuU8nZps',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::aTymc10ixqcJxpMW' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/recalculate-all-hierarchy-balances',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@recalculateAllHierarchyBalances',
        'controller' => 'App\\Http\\Controllers\\V2\\Accounting\\AccountingReportController@recalculateAllHierarchyBalances',
        'namespace' => NULL,
        'prefix' => 'api/accounting',
        'where' => 
        array (
        ),
        'as' => 'generated::aTymc10ixqcJxpMW',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::u7RlMOOPc0B08ZI7' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/service-accounts',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\ServiceAccountsController@index',
        'controller' => 'App\\Http\\Controllers\\ServiceAccountsController@index',
        'namespace' => NULL,
        'prefix' => 'api/accounting/service-accounts',
        'where' => 
        array (
        ),
        'as' => 'generated::u7RlMOOPc0B08ZI7',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::TfmCSvqFyY0RLNTK' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/service-accounts',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\ServiceAccountsController@store',
        'controller' => 'App\\Http\\Controllers\\ServiceAccountsController@store',
        'namespace' => NULL,
        'prefix' => 'api/accounting/service-accounts',
        'where' => 
        array (
        ),
        'as' => 'generated::TfmCSvqFyY0RLNTK',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::q1HYB0V5rgJ7rfJf' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/service-accounts/transfer',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\ServiceAccountsController@transfer',
        'controller' => 'App\\Http\\Controllers\\ServiceAccountsController@transfer',
        'namespace' => NULL,
        'prefix' => 'api/accounting/service-accounts',
        'where' => 
        array (
        ),
        'as' => 'generated::q1HYB0V5rgJ7rfJf',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::xo0T4XZiBdOFnAxA' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/accounting/service-accounts/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\ServiceAccountsController@update',
        'controller' => 'App\\Http\\Controllers\\ServiceAccountsController@update',
        'namespace' => NULL,
        'prefix' => 'api/accounting/service-accounts',
        'where' => 
        array (
        ),
        'as' => 'generated::xo0T4XZiBdOFnAxA',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::XZvDjlGhPUr2PzpU' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/accounting/settings',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\SettingController@getSettings',
        'controller' => 'App\\Http\\Controllers\\SettingController@getSettings',
        'namespace' => NULL,
        'prefix' => 'api/accounting',
        'where' => 
        array (
        ),
        'as' => 'generated::XZvDjlGhPUr2PzpU',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::IPLqYSJrwicnKYmJ' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/settings',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\SettingController@updateSettings',
        'controller' => 'App\\Http\\Controllers\\SettingController@updateSettings',
        'namespace' => NULL,
        'prefix' => 'api/accounting',
        'where' => 
        array (
        ),
        'as' => 'generated::IPLqYSJrwicnKYmJ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::YddSCpCozPtG8fXv' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/accounting/settings/update-existing',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\SettingController@updateExistingEntities',
        'controller' => 'App\\Http\\Controllers\\SettingController@updateExistingEntities',
        'namespace' => NULL,
        'prefix' => 'api/accounting',
        'where' => 
        array (
        ),
        'as' => 'generated::YddSCpCozPtG8fXv',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::2hcjiLtPhuy3ZC0L' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/report/order',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Report\\ReportOrder\\ReportOrderController@AllOrder',
        'controller' => 'App\\Http\\Controllers\\V2\\Report\\ReportOrder\\ReportOrderController@AllOrder',
        'namespace' => NULL,
        'prefix' => 'api/report',
        'where' => 
        array (
        ),
        'as' => 'generated::2hcjiLtPhuy3ZC0L',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::AiWAt5USlqXqf9JB' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/report/getByOrderId',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth',
        ),
        'uses' => 'App\\Http\\Controllers\\V2\\Report\\ReportOrder\\ReportOrderController@getByOrderId',
        'controller' => 'App\\Http\\Controllers\\V2\\Report\\ReportOrder\\ReportOrderController@getByOrderId',
        'namespace' => NULL,
        'prefix' => 'api/report',
        'where' => 
        array (
        ),
        'as' => 'generated::AiWAt5USlqXqf9JB',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::eEDIkItVuCpBAdGL' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'images/whatsapp-meta-default.png',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
        ),
        'uses' => 'O:47:"Laravel\\SerializableClosure\\SerializableClosure":1:{s:12:"serializable";O:46:"Laravel\\SerializableClosure\\Serializers\\Signed":2:{s:12:"serializable";s:1246:"O:46:"Laravel\\SerializableClosure\\Serializers\\Native":5:{s:3:"use";a:0:{}s:8:"function";s:1026:"function () {
    $path = \\public_path(\'images/whatsapp-meta-default.png\');
    if (\\is_readable($path)) {
        return \\response()->file($path, [
            \'Content-Type\' => \'image/png\',
            \'Cache-Control\' => \'public, max-age=604800\',
        ]);
    }

    if (\\function_exists(\'imagecreatetruecolor\')) {
        $im = \\imagecreatetruecolor(512, 512);
        $bg = \\imagecolorallocate($im, 245, 245, 245);
        \\imagefill($im, 0, 0, $bg);
        \\ob_start();
        \\imagepng($im);
        \\imagedestroy($im);
        $binary = \\ob_get_clean();

        return \\response($binary, 200, [
            \'Content-Type\' => \'image/png\',
            \'Cache-Control\' => \'public, max-age=86400\',
        ]);
    }

    $minimal = \\base64_decode(\'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==\');

    return \\response($minimal, 200, [
        \'Content-Type\' => \'image/png\',
        \'Cache-Control\' => \'public, max-age=86400\',
    ]);
}";s:5:"scope";s:37:"Illuminate\\Routing\\RouteFileRegistrar";s:4:"this";N;s:4:"self";s:32:"000000000000063b0000000000000000";}";s:4:"hash";s:44:"CiXrmixZ15NXmYXyRB+t5rV87ml3JMjD1TM9fBsonjE=";}}',
        'namespace' => NULL,
        'prefix' => '',
        'where' => 
        array (
        ),
        'as' => 'generated::eEDIkItVuCpBAdGL',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
  ),
)
);
