module.exports = { foo: 1, bar: function(){ return 42; } };
console.log('[inside module] keys:', Object.keys(module.exports));
