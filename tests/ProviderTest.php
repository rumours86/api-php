<?php
declare(strict_types=1);

namespace B2Binpay\Tests;

use B2Binpay\Coin;
use B2Binpay\CoinFactory;
use B2Binpay\Provider;
use B2Binpay\ApiInterface;
use B2Binpay\Rate;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ProviderTest extends TestCase
{
    /**
     * @var Provider
     */
    protected $provider;

    /**
     * @var CoinFactory | MockObject
     */
    protected $coinFactory;

    /**
     * @var ApiInterface | MockObject
     */
    protected $api;

    public function setUp()
    {
        $this->coinFactory = $this->createMock(CoinFactory::class);
        $this->api = $this->createMock(ApiInterface::class);

        $this->provider = new Provider(
            getenv('AUTH_KEY'),
            getenv('AUTH_SECRET'),
            true,
            null,
            $this->coinFactory,
            $this->api
        );
    }

    public function tearDown()
    {
        $this->provider = null;
        $this->coinFactory = null;
        $this->api = null;
    }

    public function testGetAuthorization()
    {
        $this->api->method('genAuthBasic')
            ->willReturn($this->getAuthBasic());

        $this->assertSame($this->getAuth(), $this->provider->getAuthorization());
    }

    public function testGetAuthToken()
    {
        $this->api->method('getAccessToken')
            ->willReturn($this->getAuthBasic());

        $this->assertSame($this->getAuthBasic(), $this->provider->getAuthToken());
    }

    public function testGetRates()
    {
        $url = 'url';
        $alpha = 'btc';
        $response = [1, 2];

        $this->api->method('getRatesUrl')
            ->willReturn($url);

        $this->api->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->equalTo('get'),
                $this->equalTo($url . $alpha)
            )
            ->willReturn($response);

        $rates = $this->provider->getRates($alpha);
        $this->assertEquals($response, $rates);
    }

    public function testGetWallets()
    {
        $url = 'url';
        $response = [1, 2];

        $this->api->method('getWalletsUrl')
            ->willReturn($url);

        $this->api->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->equalTo('get'),
                $this->equalTo($url)
            )
            ->willReturn($response);

        $wallets = $this->provider->getWallets();
        $this->assertEquals($response, $wallets);
    }

    public function testGetWallet()
    {
        $url = 'url';
        $wallet = 1;
        $response = [1, 2];

        $this->api->method('getWalletsUrl')
            ->willReturn($url);

        $this->api->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->equalTo('get'),
                $this->equalTo($url)
            )
            ->willReturn($response);

        $wallets = $this->provider->getWallet($wallet);
        $this->assertEquals($response, $wallets);
    }

    public function testConvertCurrencySame()
    {
        $amountObj = $this->createMock(Coin::class);

        $this->coinFactory->method('create')
            ->willReturn($amountObj);

        $amountObj->method('getValue')
            ->willReturn('value');

        $amount = $this->provider->convertCurrency('1', 'USD', 'USD', []);
        $this->assertSame('value', $amount);
    }

    public function testConvertCurrency()
    {
        $sum = '0.001';
        $currencyFrom = 'USD';
        $currencyTo = 'XRP';
        $isoFrom = 840;
        $isoTo = 1010;
        $rateValue = '264866406';
        $ratePow = 6;
        $result = '1234';

        $ratesStub = json_decode('{"data":[{
                "from":{"alpha":"USD","iso":840},
                "to":{"alpha":"XRP","iso":1010},
                "rate":"264866406",
                "pow":6
            }]}', false);

        $rate = new Rate($rateValue, $ratePow);

        $inputAmount = $this->createMock(Coin::class);
        $resultAmount = $this->createMock(Coin::class);

        $this->coinFactory->expects($this->once())
            ->method('create')
            ->with($this->equalTo($sum), $this->equalTo($isoFrom))
            ->willReturn($inputAmount);

        $inputAmount->expects($this->once())
            ->method('convert')
            ->with(
                $this->equalTo($rate),
                $this->equalTo($isoTo)
            )
            ->willReturn($resultAmount);

        $resultAmount->method('getValue')
            ->willReturn($result);

        $amount = $this->provider->convertCurrency($sum, $currencyFrom, $currencyTo, $ratesStub->data);
        $this->assertSame($result, $amount);
    }

    /**
     * @expectedException \B2Binpay\Exception\IncorrectRatesException
     */
    public function testIncorrectRatesException()
    {
        $amountObj = $this->createMock(Coin::class);

        $this->coinFactory->method('create')
            ->willReturn($amountObj);

        $this->provider->convertCurrency('1', 'USD', 'BTC', []);
    }

    public function testAddMarkup()
    {
        $sum = '0.001';
        $iso = 840;
        $percent = 10;
        $result = '9999';

        $amountObj = $this->createMock(Coin::class);
        $resultAmount = $this->createMock(Coin::class);

        $this->coinFactory->expects($this->once())
            ->method('create')
            ->with(
                $this->equalTo($sum),
                $this->equalTo($iso)
            )
            ->willReturn($amountObj);

        $amountObj->expects($this->once())
            ->method('percentage')
            ->with(
                $this->equalTo($percent)
            )
            ->willReturn($resultAmount);

        $resultAmount->method('getValue')
            ->willReturn($result);

        $amount = $this->provider->addMarkup($sum, 'USD', $percent);
        $this->assertSame($result, $amount);
    }

    public function testCreateBill()
    {
        $url = 'url';
        $response = [1, 2];
        $amount = '123';
        $precision = 8;
        $wallet = 1;
        $lifetime = 1200;
        $trackingId = 'trackingId';
        $callbackUrl = 'callbackUrl';

        $params = [
            'amount' => $amount,
            'wallet' => $wallet,
            'pow' => $precision,
            'lifetime' => $lifetime,
            'tracking_id' => $trackingId,
            'callback_url' => $callbackUrl
        ];

        $this->api->method('getNewBillUrl')
            ->willReturn($url);

        $amountObj = $this->createMock(Coin::class);

        $this->coinFactory->method('create')
            ->willReturn($amountObj);

        $amountObj->method('getPowed')
            ->willReturn($amount);

        $amountObj->method('getPrecision')
            ->willReturn($precision);

        $this->api->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->equalTo('post'),
                $this->equalTo($url),
                $this->equalTo($params)
            )
            ->willReturn($response);

        $bill = $this->provider->createBill($wallet, $amount, 'BTC', $lifetime, $trackingId, $callbackUrl);
        $this->assertEquals($response, $bill);
    }

    public function testGetBills()
    {
        $url = 'url';
        $wallet = 1;
        $response = [1, 2];

        $this->api->method('getBillsUrl')
            ->willReturn($url);

        $this->api->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->equalTo('get'),
                $this->equalTo($url)
            )
            ->willReturn($response);

        $bills = $this->provider->getBills($wallet);
        $this->assertEquals($response, $bills);
    }

    public function testGetBill()
    {
        $url = 'url';
        $billId = 1;
        $response = [1, 2];

        $this->api->method('getBillsUrl')
            ->willReturn($url);

        $this->api->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->equalTo('get'),
                $this->equalTo($url)
            )
            ->willReturn($response);

        $bill = $this->provider->getBill($billId);
        $this->assertEquals($response, $bill);
    }

    /**
     * @return string
     */
    private function getAuth(): string
    {
        return getenv('AUTH');
    }

    /**
     * @return string
     */
    private function getAuthBasic(): string
    {
        return getenv('AUTH_BASIC');
    }
}
