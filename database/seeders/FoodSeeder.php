<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Dish;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FoodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define Categories
        $categoriesData = [
            'Đồ Nướng',
            'Món Gà',
            'Món Chính',
            'Món Khai Vị',
            'Đồ Uống'
        ];

        $categories = [];
        $order = 1;
        foreach ($categoriesData as $catName) {
            $categories[$catName] = Category::firstOrCreate([
                'name' => $catName,
            ], [
                'status' => Category::STATUS_ACTIVE,
                'order' => $order++,
            ]);
        }

        // Define Dishes
        $dishesData = [
            [
                'name' => 'Bò nướng tảng',
                'price' => 250000,
                'description' => 'Từng miếng bò hảo hạng được nướng trên than hồng xèo xèo, thơm nức mũi, mềm mọng nước bên trong và xém cạnh giòn rụm bên ngoài. Đánh thức mọi giác quan của bạn!',
                'image' => '/image/food/bò nướng.jpg',
                'category_name' => 'Đồ Nướng',
            ],
            [
                'name' => 'Dẻ sườn heo nướng BBQ',
                'price' => 180000,
                'description' => 'Sườn non tẩm ướp sốt BBQ đậm đà, nướng chậm trên lửa nhỏ để thịt mềm tan trong miệng, tách róc xương một cách hoàn hảo. Món tủ không thể bỏ qua!',
                'image' => '/image/food/dẻ sườn heo nướng.jpg',
                'category_name' => 'Đồ Nướng',
            ],
            [
                'name' => 'Má heo nướng ngũ vị',
                'price' => 150000,
                'description' => 'Má heo giòn sần sật, thơm lừng vị mộc nhĩ và ngũ vị hương. Một trải nghiệm ẩm thực đường phố tuyệt hảo, lai rai cùng bia lạnh thì ngon bá cháy!',
                'image' => '/image/food/má heo nướng.jpg',
                'category_name' => 'Đồ Nướng',
            ],
            [
                'name' => 'Thịt bò Kobe nướng đá',
                'price' => 850000,
                'description' => 'Cực phẩm bò Kobe với vân mỡ cẩm thạch tuyệt đẹp, nướng trên đá nóng để giữ nguyên vẹn vị ngọt thanh và độ mềm tan chảy ngay trên đầu lưỡi.',
                'image' => '/image/food/thịt bò kobe.jpg',
                'category_name' => 'Đồ Nướng',
            ],
            [
                'name' => 'Cánh gà chiên giòn',
                'price' => 95000,
                'description' => 'Lớp vỏ ngoài giòn tan, vàng ươm bao bọc lấy phần thịt gà mọng nước bên trong. Cắn một miếng là thấy rộp rộp cực đã tai!',
                'image' => '/image/food/cánh gà chiên.jpg',
                'category_name' => 'Món Gà',
            ],
            [
                'name' => 'Cánh gà nướng muối ớt',
                'price' => 105000,
                'description' => 'Vị cay nồng của ớt hiểm quyện với muối hột, áo đều lên đôi cánh gà nướng than hoa vàng ruộm. Cay xé lưỡi nhưng càng ăn càng ghiền!',
                'image' => '/image/food/cánh gà nướng 2.jpg',
                'category_name' => 'Món Gà',
            ],
            [
                'name' => 'Cánh gà nướng mật ong',
                'price' => 105000,
                'description' => 'Lớp da gà căng bóng, tươm mỡ và óng ánh lớp mật ong rừng nguyên chất, vừa ngọt ngào vừa đậm vị. Sự lựa chọn hoàn hảo cho cả nhà.',
                'image' => '/image/food/cánh gà nướng.jpg',
                'category_name' => 'Món Gà',
            ],
            [
                'name' => 'Cánh gà chiên mắm',
                'price' => 110000,
                'description' => 'Siêu phẩm cánh gà chiên mắm tỏi ớt, mặn mặn ngọt ngọt, thơm lừng mùi mắm nhĩ tỏi phi, ăn cùng cơm nóng là hao cơm phải biết!',
                'image' => '/image/food/cánh gà sốt mắm.jpg',
                'category_name' => 'Món Gà',
            ],
            [
                'name' => 'Gà nướng lu nguyên con',
                'price' => 280000,
                'description' => 'Gà ta thả vườn nướng trong lu đất sét giữ nguyên độ ẩm, thịt chắc ngọt, da giòn rụm thơm mùi lá chanh và sả. Một món ngon dân dã tinh túy.',
                'image' => '/image/food/gà nướng.jpg',
                'category_name' => 'Món Gà',
            ],
            [
                'name' => 'Cơm tấm sườn bì chả',
                'price' => 65000,
                'description' => 'Hạt cơm tấm dẻo thơm, sườn nướng mỡ hành tỏa khói nghi ngút, ăn kèm bì chả làm thủ công và nước mắm chua ngọt đặc sánh. Món ăn linh hồn của Sài Gòn!',
                'image' => '/image/food/cơm tấm.jpg',
                'category_name' => 'Món Chính',
            ],
            [
                'name' => 'Pad Thái hải sản',
                'price' => 85000,
                'description' => 'Sợi hủ tiếu mềm dai xào cùng tôm mực tươi rói, cân bằng hoàn hảo giữa vị chua, cay, mặn, ngọt đặc trưng của ẩm thực Thái. Thêm chút đậu phộng rang giòn rụm.',
                'image' => '/image/food/pad thai.jpg',
                'category_name' => 'Món Chính',
            ],
            [
                'name' => 'Khoai tây chiên bơ tỏi',
                'price' => 45000,
                'description' => 'Những thanh khoai tây vàng ươm, ngoài giòn trong xốp, xóc đều với bơ tỏi thơm lừng ngây ngất. Càng ăn càng cuốn!',
                'image' => '/image/food/khoai tây chiên.jpg',
                'category_name' => 'Món Khai Vị',
            ],
            [
                'name' => 'Ngô chiên trứng muối',
                'price' => 55000,
                'description' => 'Hạt bắp nếp dẻo ngọt bọc trong lớp bột chiên giòn tan, áo thêm lớp trứng muối béo ngậy mằn mặn. Ăn chơi cũng ghiền!',
                'image' => '/image/food/ngô chiên.jpg',
                'category_name' => 'Món Khai Vị',
            ],
            [
                'name' => 'Nấm hải sản chiên giòn',
                'price' => 60000,
                'description' => 'Nấm hải sản tươi giòn sần sật, áo bột chiên vàng rộm, cắn vào nghe rôm rốp, bên trong vẫn giữ độ mọng nước tự nhiên.',
                'image' => '/image/food/nấm hải sản chiên.jpg',
                'category_name' => 'Món Khai Vị',
            ],
            [
                'name' => 'Súp Tomyum hải sản',
                'price' => 120000,
                'description' => 'Nước dùng Tomyum chua chua cay cay bùng nổ hương vị, tôm mực tươi ngon hòa quyện cùng lá chanh Thái, sả, ớt. Đánh thức vị giác ngay muỗng đầu tiên!',
                'image' => '/image/food/sup tomyum.jpg',
                'category_name' => 'Món Khai Vị',
            ],
            [
                'name' => 'Coca Cola mát lạnh',
                'price' => 20000,
                'description' => 'Hòa nhịp cuộc vui cùng một ly Coca Cola sảng khoái với bọt ga bùng nổ, đập tan mọi cơn khát!',
                'image' => '/image/food/coca cola.jpg',
                'category_name' => 'Đồ Uống',
            ],
            [
                'name' => 'Pepsi tươi',
                'price' => 20000,
                'description' => 'Khởi động cuộc vui với hương vị Pepsi nguyên bản, cực kỳ đã khát khi dùng cùng các món nướng.',
                'image' => '/image/food/pepsi.jpg',
                'category_name' => 'Đồ Uống',
            ],
            [
                'name' => 'Pepsi Không Đường',
                'price' => 20000,
                'description' => 'Thoải mái tận hưởng hương vị sảng khoái mà không lo về calo. Lựa chọn hoàn hảo cho vóc dáng!',
                'image' => '/image/food/pepsi không đường.jpg',
                'category_name' => 'Đồ Uống',
            ],
            [
                'name' => 'Nước ép dưa hấu',
                'price' => 35000,
                'description' => '100% dưa hấu tươi nguyên chất, ngọt thanh mát lạnh, giải nhiệt ngay tức thì trong những ngày oi bức.',
                'image' => '/image/food/nước ép dưa hấu.jpg',
                'category_name' => 'Đồ Uống',
            ],
            [
                'name' => 'Nước ép thơm (dứa)',
                'price' => 35000,
                'description' => 'Vị chua ngọt hài hòa từ những trái dứa mật chín mọng, giúp tiêu hóa tốt sau những bữa tiệc no nê.',
                'image' => '/image/food/nước ép dứa.jpg',
                'category_name' => 'Đồ Uống',
            ],
            [
                'name' => 'Trà Thái xanh',
                'price' => 30000,
                'description' => 'Vị trà đậm đà đặc trưng của Thái Lan hòa quyện cùng sữa béo ngậy, một thức uống khó cưỡng.',
                'image' => '/image/food/trà thái xanh.jpg',
                'category_name' => 'Đồ Uống',
            ],
            [
                'name' => 'Trà sữa Thái đỏ',
                'price' => 30000,
                'description' => 'Sắc đỏ cam hấp dẫn, hương thơm thoang thoảng của hoa hồi và gia vị quyện trong vị trà đậm đà, béo ngọt vừa phải.',
                'image' => '/image/food/trà thái đỏ.jpg',
                'category_name' => 'Đồ Uống',
            ],
        ];

        foreach ($dishesData as $dishData) {
            $cat = $categories[$dishData['category_name']];

            $localImagePath = public_path($dishData['image']);
            $s3Key = 'dishes/' . uniqid() . '_' . basename($dishData['image']);
            $s3Url = $dishData['image']; // Mặc định dùng local nếu S3 chưa được cấu hình

            if (file_exists($localImagePath)) {
                try {
                    // Đẩy file lên S3
                    \Illuminate\Support\Facades\Storage::disk('s3')->put($s3Key, file_get_contents($localImagePath));
                    $s3Url = \Illuminate\Support\Facades\Storage::disk('s3')->url($s3Key);
                } catch (\Exception $e) {
                    $this->command->error("Không thể upload {$dishData['image']} lên S3: " . $e->getMessage());
                    // Nếu lỗi S3, có thể do chưa cấu hình AWS_*, ta vẫn lưu tạm đường dẫn local hoặc set null cho key
                    $s3Key = null; 
                }
            } else {
                $this->command->error("Không tìm thấy file ảnh gốc tại: {$localImagePath}");
                $s3Key = null;
            }

            Dish::updateOrCreate([
                'name' => $dishData['name'],
            ], [
                'price' => $dishData['price'],
                'description' => $dishData['description'],
                'image' => $s3Url,
                'image_s3_key' => $s3Key,
                'status' => Dish::STATUS_AVAILABLE,
                'category_id' => $cat->id,
            ]);
        }

        $this->command->info('Đã tạo dữ liệu món ăn mẫu thành công!');
    }
}
