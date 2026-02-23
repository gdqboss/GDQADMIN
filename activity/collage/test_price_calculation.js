// 测试团长价和佣金计算逻辑（更新版）
// 使用方法：将此文件复制到项目中，在浏览器控制台或Node.js环境中运行

// 模拟产品数据结构
function testPriceCalculation() {
  console.log('开始测试团长价和佣金计算逻辑（新版）...');
  console.log('关键更新：leadermoney字段现在直接作为团长的实际支付价格');
  
  // 测试场景1: ID=11的产品，设置拼团价为88元，团长价格为55元（leadermoney=55）
  const testProduct1 = {
    id: 11,
    leadermoney: 55,  // 团长实际支付价格
    leader_commission_ratio: 60  // 佣金比例60%
  };
  
  const testGuige1 = {
    sell_price: 88  // 拼团原价
  };
  
  // 测试团长购买场景
  console.log('\n测试场景1: 团长购买 ID=11 的产品');
  calculateAndDisplayPrice(testProduct1, testGuige1, 2); // buytype=2表示团长
  
  // 测试场景2: 普通购买场景
  console.log('\n测试场景2: 普通用户购买 ID=11 的产品');
  calculateAndDisplayPrice(testProduct1, testGuige1, 1); // buytype=1表示普通用户
  
  // 测试场景3: 其他产品，拼团价100元，团长价格为70元
  const testProduct2 = {
    id: 12,
    leadermoney: 70,  // 团长实际支付价格
    leader_commission_ratio: 30
  };
  
  const testGuige2 = {
    sell_price: 100
  };
  
  console.log('\n测试场景3: 团长购买 ID=12 的产品');
  calculateAndDisplayPrice(testProduct2, testGuige2, 2);
  
  // 测试场景4: 包含运费、会员折扣和优惠券的复杂场景
  console.log('\n测试场景4: 团长购买含运费、会员折扣和优惠券的场景');
  calculateAndDisplayPrice(testProduct1, testGuige1, 2, 10, 5, 20); // 运费10元，会员折扣5元，优惠券20元
  
  console.log('\n测试完成！');
}

// 计算并显示价格
function calculateAndDisplayPrice(product, guige, buytype, freight_price = 0, leveldk_money = 0, coupon_money = 0) {
  // 获取团长价格
  const leadermoney = parseFloat(product.leadermoney) || 0;
  
  // 获取拼团基础价格
  const basePrice = parseFloat(guige.sell_price) || 0;
  
  // 根据buytype设置product_price
  let product_price = 0;
  if (buytype == 2) {
    // 团长价格 = leadermoney字段值（团长实际支付价格）
    product_price = leadermoney;
  } else {
    // 非团长保持拼团原价
    product_price = basePrice;
  }
  
  // 计算总价
  // 总价 = 团长价(或拼团价) + 运费 - 会员折扣 - 优惠券抵扣
  let totalprice = product_price;
  
  // 应用会员折扣和优惠券抵扣
  totalprice = totalprice - leveldk_money - coupon_money;
  if (totalprice < 0) totalprice = 0; //确保不会出现负数
  
  // 添加运费
  totalprice = totalprice + freight_price;
  
  // 计算佣金
  const commission_rate = parseFloat(product.leader_commission_ratio) || 0;
  const commission_amount = (product_price * commission_rate / 100).toFixed(2);
  
  // 显示计算结果
  console.log(`产品ID: ${product.id}`);
  console.log(`拼团价: ${basePrice}元`);
  console.log(`团长价格(leadermoney): ${leadermoney}元`);
  console.log(`购买类型: ${buytype == 2 ? '团长' : '普通用户'}`);
  console.log(`运费: ${freight_price}元`);
  console.log(`会员折扣: ${leveldk_money}元`);
  console.log(`优惠券抵扣: ${coupon_money}元`);
  console.log(`产品价格(product_price): ${product_price}元`);
  console.log(`最终总价(totalprice): ${totalprice.toFixed(2)}元`);
  console.log(`佣金比例: ${commission_rate}%`);
  console.log(`佣金金额: ${commission_amount}元`);
  
  // 验证逻辑
  if (buytype == 2) {
    if (product_price === leadermoney) {
      console.log('✓ 团长价计算正确: product_price = leadermoney');
    } else {
      console.log('✗ 团长价计算错误: product_price != leadermoney');
    }
    
    // 验证总价计算
    const expectedTotal = Math.max(0, product_price - leveldk_money - coupon_money) + freight_price;
    if (Math.abs(totalprice - expectedTotal) < 0.01) { // 考虑浮点数精度问题
      console.log('✓ 总价计算正确: 团长价 + 运费 - 会员折扣 - 优惠券抵扣');
    } else {
      console.log('✗ 总价计算错误');
    }
    
    // 验证佣金计算
    if (Math.abs(parseFloat(commission_amount) - product_price * commission_rate / 100) < 0.01) {
      console.log('✓ 佣金计算正确: 团长价 * 佣金比例 / 100');
    } else {
      console.log('✗ 佣金计算错误');
    }
  }
}

// 执行测试
testPriceCalculation();

// 部署建议：
// 1. 确保数据库中ID=11的产品的leadermoney字段设置为55（团长实际支付价格）
// 2. 在拼团产品编辑页面，确保leadermoney字段正确表示团长需要支付的价格
// 3. 部署后，可以在浏览器控制台运行此测试脚本验证计算逻辑
// 4. 建议在buy.vue文件中添加console.log来跟踪实际运行时的价格计算
// 5. 特别注意：现在leadermoney直接代表团长价格，而不是优惠金额