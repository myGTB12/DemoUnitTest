<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Category;
use App\Models\Franchise;
use App\Services\CategoryService;
use Mockery;
use Exception;
use Tests\Traits\AuthenticationRequest;

class CategoryControllerTest extends TestCase
{
    use AuthenticationRequest;
    private string $url = "/api/get_category";
    private $user;
    private $franchiseId;
    public function setUp(): void
    {
        parent::setUp();
        $franchise = Franchise::factory()->create();
        $category = Category::factory()->make();
        $category = Category::create([
            "franchise_id" => $franchise->id,
            "name" => $category->name,
            "description" => $category->description,
            "remarks" => $category->remarks,
            'display_rank' => $category->display_rank
        ]);
        $password = 'password123';
        $this->user = User::firstOrCreate(['email' => 'test1@example.com', 'password' => Hash::make('password123')]);
        $this->setPassword($password);
        $this->franchiseId = $category->franchise_id;
    }
    protected function tearDown(): void
    {
        $this->user->delete();
        Franchise::query()->delete();
        Category::query()->delete();
        parent::tearDown();
    }
    public function test_get_category_data_response()
    {
        $response = $this->json('get', $this->url, ['franchise_id' => $this->franchiseId]);
        $response->assertStatus(200);
        $response->assertJson(['status' => config('common.status_response.success'), 'message' => NULL,]);
        $response->assertJsonStructure([
            'data' => [
                '*' => ["id", "name", "description", "remarks"]
            ]
        ]);
    }
    public function test_get_category_empty_data_response()
    {
        $response = $this->json('get', $this->url, ['franchise_id' => 'siu']);
        $response->assertStatus(200);
        $response->assertJson(['status' => 'Success', 'data' => []]);
    }
    public function test_get_category_data_response_fails_exception()
    {
        $this->instance(CategoryService::class, Mockery::mock(CategoryService::class, function ($mock) {
            $mock->shouldReceive('getCategory')->andThrow(new Exception());
        }));
        $response = $this->json('get', $this->url, ['franchise_id' => $this->franchiseId]);
        $response->assertStatus(400);
        $response->assertJson(['status' => 'Error', 'message' => '処理に問題が発生しました。時間をおいて再度お試しください。', 'code' => 'EUA000', 'data' => NULL]);
    }
    public function test_get_category_data_response_fails_if_franchise_id_have_not_input()
    {
        $response = $this->json('get', $this->url);
        $response->assertStatus(400);
        $response->assertJson(['status' => 'Error', 'message' => 'フランチャイジーIDが未設定です。', 'code' => 'EUA029_001', 'data' => NULL,]);
    }
}
