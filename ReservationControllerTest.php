<?php

namespace Tests\Unit;

use App\Enums\OperationStatus;
use App\Enums\ReservationStatus;
use Tests\TestCase;
use Tests\LoginAPITrait;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Reservation;
use App\Models\ReservationDetail;
use App\Models\Vehicle;
use Illuminate\Support\Str;
use App\Enums\VehicleStatus;
use App\Exceptions\BusinessException;
use App\Models\Category;
use App\Models\Config;
use App\Models\Franchise;
use App\Models\Operation;
use App\Models\Pack;
use App\Models\Policy;
use App\Models\Prefecture;
use App\Models\Station;
use App\Models\SubDriver;
use App\Models\UserPayment;
use App\Models\VehicleAccessory;
use App\Models\VehicleDetail;
use App\Models\VehicleInsurance;
use App\Models\VehicleLock;
use App\Models\VehicleModels;
use App\Repositories\Eloquent\ReservationRepository;
use App\Services\BookingService;
use App\Services\Freekey\FreekeyService;
use App\Services\Freekey\Response as FreekeyResponse;
use App\Services\Gmo\Core\Response;
use App\Services\GmoService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Exception;
use Mockery;
use Mockery\MockInterface;

class ReservationControllerTest extends TestCase
{
    use LoginAPITrait;
    private string $url = "/api/createreservation";
    private $user;
    protected $vehicle;
    protected $reservation;
    protected $reservation1;
    protected $operation;
    protected $pack;
    protected $station;
    protected $franchise;
    protected $police;
    protected $userPayment;
    protected $vehicleInsurance;
    protected $vehicleAccessory;
    protected $freeKeyService;
    protected $vehicleLock;
    public function setUp(): void
    {
        parent::setUp();
        $faker = \Faker\Factory::create();
        $user = User::query()->firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'password' => Hash::make('password123')
        ]);
        $userPayment = UserPayment::firstOrCreate([
            'user_id' => $user->id,
            'card_seq' =>  1,
            'status' => 1,
        ]);
        $franchise = Franchise::firstOrCreate([
            'name' => $faker->name,
            'address' => $faker->address,
            'phone' => $faker->phoneNumber,
            'company_code' => 9,
            'status' => 1,
        ]);
        $police = Policy::query()->firstOrCreate([
            'title' => 'title'
        ], [
            'title' => 'test',
        ]);
        $prefectures = Prefecture::firstOrCreate([
            'franchise_id' => $franchise->id,
            'name_en' => 'saigon8',
            'name_jp' => 'saigon8'
        ]);
        $station = Station::firstOrCreate([
            'prefecture_id' => $prefectures->id,
            'name_en' => $faker->address,
            'name_jp' => $faker->address,
            'address' => $faker->address,
            'latitude' => 9,
            'longitude' => 12,
            'status' => 1,
            'franchise_id' => $franchise->id,
            'gmo_shop_id' => 'tshop00057233',
            'gmo_shop_pass' => 'u65ezqqm',
            'start_code_type' => 1,
            'end_code_type' => 1
        ]);
        $category = Category::factory()->make();
        $category = Category::create([
            "franchise_id" => $franchise->id,
            "name" => $category->name,
            "description" => $category->description,
            "remarks" => $category->remarks,
            'display_rank' => $category->display_rank
        ]);
        $vehicle = Vehicle::firstOrCreate([
            "station_id" => $station->id,
            "status" => VehicleStatus::AVAILABLE->value,
        ]);
        $vehicleLock = VehicleLock::firstOrCreate([
            "vehicle_id" => $vehicle->id,
            "freekey_lock_id" => "8257b104-a1a7-4701-99a9-a34a199b65f9",
        ]);
        $vehicleModel = VehicleModels::firstOrCreate([
            "category_id" => $category->id,
            "brand" => Str::random(20),
            "name" => Str::random(20),
            "img" => Str::random(20),
            "capacity" => 1,
            "displacement" => 11.2,
            "fuel_type" => 11.2,
            "length" => 11.2,
            "width" => 11.2,
            "height" => 11.2,
            "remarks" => Str::random(20),
            "display_rank" => 1,
        ]);
        VehicleDetail::firstOrCreate([
            "vehicle_id" => $vehicle->id,
            "img" => Str::random(20),
            "img2" => Str::random(20),
            "img3" => Str::random(20),
            "img4" => Str::random(20),
            "vehicle_model_id" => $vehicleModel->id,
            "remarks" => Str::random(20),
            "display_rank" => 11,
            "wa_license_reg_date" => Carbon::now(),
            "wa_license_cancel_date" => Carbon::now(),
            "vehicle_number" => rand(0000000000, 11111111111)
        ]);
        $vehicleInsurance = VehicleInsurance::firstOrCreate([
            "name" => Str::random(20),
            "description" => Str::random(20),
            "price" => 20,
        ]);
        $vehicleAccessory = VehicleAccessory::firstOrCreate([
            'vehicle_id' => $vehicle->id,
            "name" => Str::random(20),
            "description" => Str::random(20),
            "price" => 20,
        ]);
        $reservation = Reservation::firstOrCreate([
            'user_id' => $user->id,
            'unit_price' => 100,
            'insurance_fee' => 200,
            'usage_fee' => 200,
            'user_payment_id' => $userPayment->id,
            'vehicle_id' => $vehicle->id,
            'station_start_id' => $station->id,
            'station_end_id' => $station->id,
            'start_time' => Carbon::now()->format('Y-m-d H:i:s'),
            'end_time' => Carbon::now()->addHours(1)->format('Y-m-d H:i:s'),
            'status' => ReservationStatus::NOT_PAID->value
        ]);
        $reservation1 = Reservation::firstOrCreate([
            'user_id' => $user->id,
            'unit_price' => 100,
            'insurance_fee' => 200,
            'usage_fee' => 200,
            'user_payment_id' => $userPayment->id,
            'vehicle_id' => $vehicle->id,
            'freekey_reservation_id' => $reservation->id,
            'station_start_id' => $station->id,
            'station_end_id' => $station->id,
            'start_time' => Carbon::now()->addHours(2)->format('Y-m-d H:i:s'),
            'end_time' => Carbon::now()->addHours(3)->format('Y-m-d H:i:s'),
            'status' => ReservationStatus::PAID->value,
        ]);
        SubDriver::firstOrCreate([
            'reservation_id' => $reservation1->id,
            'sub_driver_id' => $user->id,
            'email' => 'test@gmail.com'
        ]);
        $pack = Pack::firstOrCreate([
            'franchise_id' => $franchise->id,
            'name' =>  $faker->name,
            'valid_start_date' => Carbon::now()->format('Y/m'),
            'valid_end_date' => Carbon::now()->addHours(1)->format('Y/m'),
        ]);
        $operation = Operation::firstOrCreate([
            "reservation_id" => $reservation->id,
            'start_time' => Carbon::now()->addHours(2)->format('Y-m-d H:i:s'),
            'end_time' => Carbon::now()->addHours(3)->format('Y-m-d H:i:s'),
            'status' => OperationStatus::START_RENTER->value
        ]);
        ReservationDetail::firstOrCreate([
            "reservation_id" => $reservation->id,
            "vehicle_service_id" => $vehicleInsurance->id,
            "type" => "1"
        ]);
        ReservationDetail::firstOrCreate([
            "reservation_id" => $reservation1->id,
            "vehicle_service_id" => $vehicleAccessory->id,
            "type" => "2"
        ]);
        Config::firstOrCreate([
            "key" => 'app_download_url',
            "value" => "2"
        ]);
        $this->operation = $operation;
        $this->reservation = $reservation;
        $this->reservation1 = $reservation1;
        $this->vehicle = $vehicle;
        $this->user =  $user;
        $this->pack = $pack;
        $this->station = $station;
        $this->franchise = $franchise;
        $this->police = $police;
        $this->userPayment = $userPayment;
        $this->vehicleInsurance = $vehicleInsurance;
        $this->vehicleAccessory = $vehicleAccessory;
        $this->vehicleLock = $vehicleLock;
        $this->freeKeyService = new FreekeyService();
    }

    protected function tearDown(): void
    {
        $this->user->delete();
        Reservation::query()->delete();
        ReservationDetail::query()->delete();
        Vehicle::query()->delete();
        parent::tearDown();
    }
    // Test api UA-015_Get_Reservation_List
    public function test_list_reservation()
    {
        $token = $this->signInAPI();
        $response = $this->getJson('api/reservation_list?new_version=1', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            "status" => "Success",
            "message" => NULL,
        ]);
    }
    public function test_list_reservation_error_exception()
    {
        $response = $this->mock(ReservationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('reservationList')->andThrow(new Exception());
        });
        $token = $this->signInAPI();
        $response = $this->getJson('api/reservation_list?new_version=1', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "処理に問題が発生しました。時間をおいて再度お試しください。",
            'code' => 'EUA000',
            'data' => NULL,
        ]);
    }

    public function test_list_reservation_error_business_exception()
    {
        $response = $this->mock(ReservationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('reservationList')->andThrow(new BusinessException('EUA000'));
        });
        $token = $this->signInAPI();
        $response = $this->getJson('api/reservation_list?new_version=1', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "処理に問題が発生しました。時間をおいて再度お試しください。",
            'code' => 'EUA000',
            'data' => NULL,
        ]);
    }

    public function test_list_reservation_error_exception_service()
    {
        $response = $this->mock(ReservationRepository::class, function (MockInterface $mock) {
            $mock->shouldReceive('reservationList')->andThrow(new Exception());
        });
        $token = $this->signInAPI();
        $response = $this->getJson('api/reservation_list?new_version=1', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "処理に問題が発生しました。時間をおいて再度お試しください。",
            'code' => 'EUA000',
            'data' => NULL,
        ]);
    }

    // Test api UA-016_Get_Reservation_History_List

    public function test_reservation_history_list()
    {
        $token = $this->signInAPI();
        $response = $this->getJson('api/reservation_history_list', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            "status" => "Success",
            "message" => NULL,
        ]);
    }

    public function test_reservation_history_list_error_exception()
    {
        $response = $this->mock(ReservationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('reservationList')->andThrow(new Exception());
        });
        $token = $this->signInAPI();
        $response = $this->getJson('api/reservation_history_list', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "処理に問題が発生しました。時間をおいて再度お試しください。",
            'code' => 'EUA000',
            'data' => NULL,
        ]);
    }

    public function test_reservation_history_list_business_exception()
    {
        $response = $this->mock(ReservationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('reservationHistoryList')->andThrow(new BusinessException('EUA000'));
        });
        $token = $this->signInAPI();
        $response = $this->getJson('api/reservation_history_list', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "処理に問題が発生しました。時間をおいて再度お試しください。",
            'code' => 'EUA000',
            'data' => NULL,
        ]);
    }

    public function test_reservation_history_list_exception()
    {
        $instance = app()->make(ReservationRepository::class);
        $mock = Mockery::mock($instance);
        $mock->shouldReceive('reservationHistoryList')->andThrow(new Exception());
        $this->instance(ReservationRepository::class, $mock);
        $token = $this->signInAPI();
        $response = $this->getJson('api/reservation_history_list', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "処理に問題が発生しました。時間をおいて再度お試しください。",
            'code' => 'EUA000',
            'data' => NULL,
        ]);
    }

    // Test api UA-014 cencal reservation

    public function test_cancel_reservation1()
    {
        $formData = [
            'reservation_id' => $this->reservation1->id,
            'cancel_amount' => 0,
        ];
        // mock
        $instance = app()->make(BookingService::class);
        $mock = Mockery::mock($instance);
        $mock->shouldReceive('cancelReservation')->andReturn(true);
        $this->instance(BookingService::class, $mock);
        // end mock
        $token = $this->signInAPI();

        $response = $this->postJson('api/cancel_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            "status" => "Success",
            "data" => null
        ]);
    }

    public function test_cancel_reservation_error_delete()
    {
        $token = $this->signInAPI();
        $formData = [
            'reservation_id' => $this->reservation1->id,
            'cancel_amount' => 0,
        ];

        $response = $this->postJson('api/cancel_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "data" => null
        ]);
    }

    public function test_cancel_reservation_cancel_policy()
    {
        $token = $this->signInAPI();
        $faker = \Faker\Factory::create();
        User::where('id', $this->user->id)->update(['last_name' => $faker->name, 'first_name' => $faker->name]);
        Reservation::where('id', $this->reservation1->id)->update(['cancel_policy' => '{"valid_start_date":"20110301","valid_end_date":"20240103","max_amount":2,"without_permission":43,"cancel_plan":[{"day_number":1,"rate":0}]}']);
        $formData = [
            'reservation_id' => $this->reservation1->id,
            'cancel_amount' => -1,
        ];

        $response = $this->postJson('api/cancel_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "data" => null
        ]);
    }
    public function test_cancel_reservation_error_cancel_amount_1()
    {
        $token = $this->signInAPI();
        $formData = [
            'reservation_id' => $this->reservation1->id,
            'cancel_amount' => 1,
        ];

        $response = $this->postJson('api/cancel_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "data" => null
        ]);
    }

    public function test_cancel_reservation_error()
    {
        $token = $this->signInAPI();
        $formData = [
            'reservation_id' => '1',
            'cancel_amount' => '2',
        ];
        $response = $this->postJson('api/cancel_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "処理に問題が発生しました。時間をおいて再度お試しください。",
            "code" => "E000",
            "data" => null
        ]);
    }

    public function test_cancel_reservation_error_1()
    {
        $token = $this->signInAPI();
        $formData = [
            'reservation_id' => $this->reservation->id,
            'cancel_amount' => 0,
        ];
        $response = $this->post('api/cancel_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => 'この予約は利用開始したため、キャンセルできません。',
            "code" => "EUA014_004",
            "data" => null
        ]);
    }

    //test BookingService function cancelReservation
    public function test_cancel_reservation_error_2()
    {
        $token = $this->signInAPI();
        $formData = [
            'reservation_id' => $this->reservation1->id,
            'cancel_amount' => 0,
        ];
        $response = $this->postJson('api/cancel_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => '処理に問題が発生しました。時間をおいて再度お試しください。',
            "code" => "E000",
            "data" => null
        ]);
    }

    public function test_cancel_reservation_error_3()
    {
        $token = $this->signInAPI();
        $reservation = Reservation::find($this->reservation->id);
        $reservation->status = 3;
        $reservation->save();
        $formData = [
            'reservation_id' => $this->reservation->id,
            'cancel_amount' => 0,
        ];
        $response = $this->postJson('api/cancel_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => 'この予約は既にキャンセルしました。',
            "code" => "EUA014_003",
            "data" => null
        ]);
    }

    public function test_cancel_reservation_error_4()
    {
        $formData = [
            'reservation_id' => $this->reservation1->id,
            'cancel_amount' => 0,
        ];
        //mock
        $instance1 = app()->make(ReservationRepository::class);
        $mock1 = Mockery::mock($instance1);
        $mock1->shouldReceive('updateReservation')->andReturn(NULL);
        $this->instance(ReservationRepository::class, $mock1);
        //end mock

        $token = $this->signInAPI();

        $response = $this->postJson('api/cancel_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => 'この予約はキャンセルできません。',
            "code" => "EUA014_002",
            "data" => null
        ]);
    }

    // Test api UA-013 Create Reservation

    public function test_empty_input()
    {
        $token = $this->signInAPI();
        $formData = [
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "利用開始日時が指定されていません。",
            "code" => "EUA013_001",
            "data" => null
        ]);
    }

    public function test_reservation_start_time_not_input()
    {
        $token = $this->signInAPI();
        $formData = [
            //'start_time' => '2022/08/02 15:12',
            'end_time' => '2023/08/02 15:12',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "利用開始日時が指定されていません。",
            "code" => "EUA013_001",
            "data" => null
        ]);
    }

    public function test_reservation_start_time_wrong_formart_input()
    {
        $token = $this->signInAPI();
        $formData = [
            'start_time' => '2022/08/02 ',
            'end_time' => '2022/08/02 15:12',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "利用開始日時のフォーマットが間違っています。",
            "code" => "EUA013_008",
            "data" => null
        ]);
    }

    public function test_reservation_end_time_not_input()
    {
        $token = $this->signInAPI();
        $formData = [
            'start_time' => '2022/08/02 15:12',
            //'end_time' => '2022/08/02 15:12',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "返却日時が指定されていません。",
            "code" => "EUA013_002",
            "data" => null
        ]);
    }


    public function test_reservation_end_time_wrong_formart_input()
    {
        $token = $this->signInAPI();
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "返却日時は利用開始日時以降をご指定ください。",
            "code" => "EUA013_007",
            "data" => null
        ]);
    }


    public function test_reservation_end_time_after_start_time_input()
    {
        $token = $this->signInAPI();
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/01 15:10',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "返却日時は利用開始日時以降をご指定ください。",
            "code" => "EUA013_007",
            "data" => null
        ]);
    }

    public function test_reservation_vehicle_id_not_input()
    {
        $token = $this->signInAPI();
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15:14',
            // 'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "車両が選択されていません。",
            "code" => "EUA013_005",
            "data" => null
        ]);
    }

    public function test_station_start_id_not_input()
    {
        $token = $this->signInAPI();
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15:14',
            'vehicle_id' => $this->vehicle->id,
            // 'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "乗車地が選択されていません。",
            "code" => "EUA013_003",
            "data" => null
        ]);
    }

    public function test_station_end_id_not_input()
    {
        $token = $this->signInAPI();
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15:14',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            // 'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            'inssurance_fee' => 100,
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "返却地が選択されていません。",
            "code" => "EUA013_004",
            "data" => null
        ]);
    }

    public function test_user_payments_id_not_input()
    {
        $token = $this->signInAPI();
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15:14',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            // 'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "決済方法を選択してください。",
            "code" => "EUA013_006",
            "data" => null
        ]);
    }

    public function test_usage_fee_not_input()
    {
        $token = $this->signInAPI();
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15:14',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            // 'usage_fee' => 100,
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            "status" => "Error",
            "message" => "利用金額がありません。",
            "code" => "EUA013_010",
            "data" => null
        ]);
    }

    public function test_create_reservation_list_sub_driver()
    {
        $token = $this->signInAPI();
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15:14',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            "pack_id" => $this->pack->id,
            "pack_name" => $this->pack->name,
            "unit_price" => 100,
            "list_sub_driver" => [
                [
                    "id" => $this->user->id,
                    "email" => $this->user->email,
                ],
                [
                    "id" => $this->user->id,
                    "email" => $this->user->email,
                ]
            ],
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'E000',
        ]);
    }

    public function test_create_reservation_vehicle_insurance_calc_type_1()
    {
        $token = $this->signInAPI();
        VehicleInsurance::where('id', $this->vehicleInsurance->id)->update(['calc_type' => 1]);
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15:14',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            "pack_id" => $this->pack->id,
            "pack_name" => $this->pack->name,
            "unit_price" => 100,
            "vehicle_insurance_id" => [
                $this->vehicleInsurance->id
            ],
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'E000',
        ]);
    }

    public function test_create_reservation_vehicle_insurance_calc_type_2()
    {
        $token = $this->signInAPI();
        VehicleInsurance::where('id', $this->vehicleInsurance->id)->update(['calc_type' => 2]);
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15:14',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            "pack_id" => $this->pack->id,
            "pack_name" => $this->pack->name,
            "unit_price" => 100,
            "vehicle_insurance_id" => [
                $this->vehicleInsurance->id
            ],
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'E000',
        ]);
    }

    public function test_create_reservation_vehicle_option_id_list_calc_type_1()
    {
        $token = $this->signInAPI();
        Station::where('id', $this->station->id)->update(['start_code_type' => 2]);
        Station::where('id', $this->station->id)->update(['end_code_type' => 2]);
        VehicleAccessory::where('id', $this->vehicleAccessory->id)->update(['calc_type' => 1]);
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15:14',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            "pack_id" => $this->pack->id,
            "pack_name" => $this->pack->name,
            "unit_price" => 100,
            "vehicle_option_id_list" => [[
                "id" => $this->vehicleAccessory->id,
                "quantity" => 1233,
            ]],
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'E000',
        ]);
    }

    public function test_create_reservation_vehicle_option_id_list_calc_type_2()
    {
        $token = $this->signInAPI();
        Station::where('id', $this->station->id)->update(['start_code_type' => 3]);
        Station::where('id', $this->station->id)->update(['end_code_type' => 3]);
        VehicleAccessory::where('id', $this->vehicleAccessory->id)->update(['calc_type' => 2]);
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15:14',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            "pack_id" => $this->pack->id,
            "pack_name" => $this->pack->name,
            "unit_price" => 100,
            "vehicle_option_id_list" => [[
                "id" => $this->vehicleAccessory->id,
                "quantity" => 1233,
            ]],
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'E000',
        ]);
    }

    public function test_create_reservation_exist()
    {
        $formData = [
            'start_time' => '2023/02/02 15:12',
            'end_time' => '2023/08/02 15:14',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            "pack_id" => $this->pack->id,
            "pack_name" => $this->pack->name,
            "unit_price" => 100,
            'new_version' => 1,
        ];
        $token = $this->signInAPI();
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'message' => 'この時間に既に予約があります。',
            'code' => 'EUA013_012',
        ]);
    }
    public function test_create_reservation_mock_return_false()
    {
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15:14',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            "pack_id" => $this->pack->id,
            "pack_name" => $this->pack->name,
            "unit_price" => 100,
            'new_version' => 1,
        ];

        $instance = app()->make(BookingService::class);
        $mock = Mockery::mock($instance);
        $mock->shouldReceive('createReservation')->andReturn(false);
        $this->instance(BookingService::class, $mock);
        $token = $this->signInAPI();
        //end mock
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'message' => '処理に問題が発生しました。時間をおいて再度お試しください。',
        ]);
    }

    public function test_create_reservation_mock_success()
    {
        $formData = [
            'start_time' => '2022/08/02 15:12',
            'end_time' => '2022/08/02 15:14',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->user->id,
            'usage_fee' => 100,
            "pack_id" => $this->pack->id,
            "pack_name" => $this->pack->name,
            "unit_price" => 100,
            'new_version' => 1,
        ];

        $instance = app()->make(BookingService::class);
        $mock = Mockery::mock($instance);
        $mock->shouldReceive('createReservation')->andReturn();
        $this->instance(BookingService::class, $mock);
        $token = $this->signInAPI();
        //end mock
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'status'  => "Success",
            'message' => NULL,
            'data' => NULL,
        ]);
    }

    public function test_create_reservation_success()
    {
        $token = $this->signInAPI();
        //mock gmo

        $this->instance(GmoService::class, Mockery::mock(GmoService::class, function ($mock) {
            $mock->shouldReceive('entryTransactionPayment')->andReturn(new Response(new \GuzzleHttp\Psr7\Response, []));
        }));
        //mock freekey
        $this->instance(FreekeyService::class, Mockery::mock(FreekeyService::class, function ($mock) {
            $mock->shouldReceive('getToken')->andReturn(new FreekeyResponse(new \GuzzleHttp\Psr7\Response, []));
        }));
        //end mock freekey
        $formData = [
            'start_time' => '2023/12/29 19:20',
            'end_time' => '2023/12/30 19:20',
            'vehicle_id' => $this->vehicle->id,
            'station_start_id' => $this->station->id,
            'station_end_id' => $this->station->id,
            'user_payments_id' => $this->userPayment->id,
            'usage_fee' => 100,
            "pack_id" => $this->pack->id,
            "pack_name" => $this->pack->name,
            "unit_price" => 100,
            "list_sub_driver" => 'list_sub_driver',
            "vehicle_insurance_id" => 'vehicle_insurance_id',
            "vehicle_option_id_list" => 'vehicle_option_id_list',
            'new_version' => 1,
        ];
        $response = $this->postJson('api/create_reservation', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'status'  => "Success",
            'message' => null
        ]);
        $response->assertJsonStructure([
            'status',
            'message',
            'data'
        ]);
    }

    // Test api UA-038_Check valid sub driver
    public function test_check_valid_sub_driver_email_does_not_exist()
    {
        $token = $this->signInAPI();
        $formData = [
            'sub_driver_email_list' => [
                'test1@gmail.com',
                'test2@gmail.com',
                'test3@gmail.com'

            ]
        ];
        $response = $this->postJson('api/check_valid_sub_driver', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'EUA038_004',
        ]);
    }

    public function test_check_valid_sub_driver_email_exist_error()
    {
        $token = $this->signInAPI();
        $formData = [
            'sub_driver_email_list' => [
                'test@example.com'

            ]
        ];
        $response = $this->postJson('api/check_valid_sub_driver', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'EUA038_005',
            'message' => '自分のメールアドレスは追加運転者に設定できません。'
        ]);
    }

    public function test_check_valid_sub_driver_email_string()
    {
        $token = $this->signInAPI();
        $formData = [
            'sub_driver_email_list' => 'test@example.com'
        ];
        $response = $this->postJson('api/check_valid_sub_driver', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'EUA038_002',
            'message' => '追加運転者のメールアドレスを入力してください。'
        ]);
    }

    public function test_check_valid_sub_driver_email_error_validate()
    {
        $token = $this->signInAPI();
        $formData = [
            'sub_driver_email_list' => [
                'testexample.com'

            ]
        ];
        $response = $this->postJson('api/check_valid_sub_driver', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'EUA038_001',
            'message' => '入力した情報は正しくないメールがあります。'
        ]);
    }

    public function test_check_valid_sub_driver_email_unique()
    {
        $token = $this->signInAPI();
        $formData = [
            'sub_driver_email_list' => [
                'test@example.com',
                'test@example.com'
            ]
        ];
        $response = $this->postJson('api/check_valid_sub_driver', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'EUA038_003',
            'message' => '重複するメールアドレスがあります。再度ご確認ください。'
        ]);
    }

    public function test_check_valid_sub_driver_error()
    {
        $formData = [
            'sub_driver_email_list' => [
                'test11@example.com',
            ]
        ];

        $instance = app()->make(BookingService::class);
        $mock = Mockery::mock($instance);
        $mock->shouldReceive('checkSubDriver')->andThrow(new Exception());
        $this->instance(BookingService::class, $mock);
        $token = $this->signInAPI();
        $response = $this->postJson('api/check_valid_sub_driver', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'E000',
            'message' => '処理に問題が発生しました。時間をおいて再度お試しください。'
        ]);
    }

    public function test_check_valid_sub_driver_service()
    {
        $token = $this->signInAPI();
        User::firstOrCreate([
            'email' => 'test11@example.com',
            'password' => Hash::make('password123')
        ]);
        $formData = [
            'sub_driver_email_list' => [
                'test11@example.com',
            ]
        ];
        $response = $this->postJson('api/check_valid_sub_driver', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'status'  => "Success",
            'message' => NULL
        ]);
    }

    // Test api UA-040_Cancel Calculation

    public function test_cancel_calc_validate()
    {
        $token = $this->signInAPI();
        $formData = [];
        $response = $this->postJson('api/cancel_calc', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'EUA040_001',
            'message' => '予約IDが未設定です。'
        ]);
    }

    public function test_cancel_calc_error()
    {
        $token = $this->signInAPI();
        $formData = [
            'reservation_id' => $this->reservation->id,
        ];
        $response = $this->postJson('api/cancel_calc', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'code' => 'E000'
        ]);
    }

    public function test_cancel_calc_valid_start_date_null()
    {
        $token = $this->signInAPI();
        Reservation::where('id', $this->reservation1->id)->update(['cancel_policy' => '{"valid_end_date":"2021/01","max_amount":2,"without_permission":43,"cancel_plan":{"day_number":1,"rate":0}}']);
        $formData = [
            'reservation_id' => $this->reservation1->id,
        ];
        $response = $this->postJson('api/cancel_calc', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'status'  => "Success",
            'message' => NULL
        ]);
    }

    public function test_cancel_calc_day_number_0()
    {
        $token = $this->signInAPI();
        Reservation::where('id', $this->reservation1->id)->update(['cancel_policy' => '{"valid_start_date":"20110301","valid_end_date":"20240103","max_amount":2,"without_permission":43,"cancel_plan":[{"day_number":0,"rate":0}]}']);
        $formData = [
            'reservation_id' => $this->reservation1->id,
        ];
        $response = $this->postJson('api/cancel_calc', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'status'  => "Success",
            'message' => NULL
        ]);
    }

    public function test_cancel_calc_day_number_1()
    {
        $token = $this->signInAPI();
        Reservation::where('id', $this->reservation1->id)->update(['cancel_policy' => '{"valid_start_date":"20110301","valid_end_date":"20240103","max_amount":2,"without_permission":43,"cancel_plan":[{"day_number":1,"rate":0}]}']);
        $formData = [
            'reservation_id' => $this->reservation1->id,
        ];
        $response = $this->postJson('api/cancel_calc', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'status'  => "Success",
            'message' => NULL
        ]);
    }

    public function test_cancel_calc_day_number_0_rate_1()
    {
        $token = $this->signInAPI();
        Reservation::where('id', $this->reservation1->id)->update(['cancel_policy' => '{"valid_start_date":"20110301","valid_end_date":"20240103","max_amount":1,"without_permission":43,"cancel_plan":[{"day_number":0,"rate":1}]}']);
        $formData = [
            'reservation_id' => $this->reservation1->id,
        ];
        $response = $this->postJson('api/cancel_calc', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'status'  => "Success",
            'message' => NULL
        ]);
    }

    public function test_cancel_calc_success()
    {
        $token = $this->signInAPI();
        Reservation::where('id', $this->reservation1->id)->update(['cancel_policy' => '{"valid_start_date":"2023/02","valid_end_date":"2023/03","max_amount":2,"without_permission":43,"cancel_plan":{"day_number":0,"rate":0}}']);
        $formData = [
            'reservation_id' => $this->reservation1->id,
        ];
        $response = $this->postJson('api/cancel_calc', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'status'  => "Success",
            'message' => NULL
        ]);
    }

    // Test UA-042_Check start end code

    public function test_check_start_end_code_param_null()
    {
        $token = $this->signInAPI();
        $formData = [];
        $response = $this->postJson('api/check_start_end_code', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'message' => '予約IDが未設定です。',
            'code' => 'EUA042_001',

        ]);
    }

    public function test_check_start_end_code_validate_reservation_id()
    {
        $token = $this->signInAPI();
        $formData = [
            // "reservation_id" => $this->reservation1->id,
            "code" => "1234",
            "type" => 1,
        ];
        $response = $this->postJson('api/check_start_end_code', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'message' => '予約IDが未設定です。',
            'code' => 'EUA042_001',

        ]);
    }

    public function test_check_start_end_code_validate_type()
    {
        $token = $this->signInAPI();
        $formData = [
            "reservation_id" => $this->reservation1->id,
            "code" => "1234",
            // "type" => 1,
        ];
        $response = $this->postJson('api/check_start_end_code', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'message' => '区分が未設定です。',
            'code' => 'EUA042_003',

        ]);
    }
    public function test_check_start_end_code_service_error()
    {
        $token = $this->signInAPI();
        $formData = [
            "reservation_id" => $this->reservation1->id,
            "code" => "1234",
            "type" => 1,
        ];
        $response = $this->postJson('api/check_start_end_code', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'message' => '利用開始コードが間違っています。',
            'code' => 'EUA042_006'
        ]);
    }

    public function test_check_start_end_code_reservation_id_error()
    {
        $token = $this->signInAPI();
        $formData = [
            "reservation_id" => 1,
            "code" => "1234",
            "type" => 1,
        ];
        $response = $this->postJson('api/check_start_end_code', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'message' => '予約が存在しません。',
            'code' => 'EUA042_005',

        ]);
    }

    public function test_check_start_end_code_error_type_2()
    {
        $token = $this->signInAPI();
        Reservation::where('id', $this->reservation1->id)->update(['start_code' => '1234']);
        $formData = [
            "reservation_id" => $this->reservation1->id,
            "code" => "1234",
            "type" => 2,
        ];
        $response = $this->postJson('api/check_start_end_code', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'message' => '利用終了コードが間違っています。',
            'code' => 'EUA042_007',
        ]);
    }

    public function test_check_start_end_code_success_end_code()
    {
        $token = $this->signInAPI();
        Reservation::where('id', $this->reservation1->id)->update(['end_code' => '1234']);
        $formData = [
            "reservation_id" => $this->reservation1->id,
            "code" => "1234",
            "type" => 2,
        ];
        $response = $this->postJson('api/check_start_end_code', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'status'  => "Success",
            'message' => NULL
        ]);
    }

    public function test_check_start_end_code_error_exception()
    {
        Reservation::where('id', $this->reservation1->id)->update(['end_code' => '1234']);
        $formData = [
            "reservation_id" => $this->reservation1->id,
            "code" => "1234",
            "type" => 2,
        ];
        $response = $this->mock(ReservationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('checkCode')->andThrow(new Exception());
        });
        $token = $this->signInAPI();
        $response = $this->postJson('api/check_start_end_code', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(400);
        $response->assertJson([
            'status'  => "Error",
            'message' => '処理に問題が発生しました。時間をおいて再度お試しください。',
            'code' => 'E000',
            'data' => NULL,
        ]);
    }

    public function test_check_start_end_code_success()
    {
        $token = $this->signInAPI();
        Reservation::where('id', $this->reservation1->id)->update(['start_code' => '1234']);
        $formData = [
            "reservation_id" => $this->reservation1->id,
            "code" => "1234",
            "type" => 1,
        ];
        $response = $this->postJson('api/check_start_end_code', $formData, [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'status'  => "Success",
            'message' => NULL
        ]);
    }
}
