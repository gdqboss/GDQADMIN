// 全局翻译系统
var Translation = {
    currentLang: localStorage.getItem('lang') || 'zh',
    translations: {},
    
    // 初始化
    init: function() {
        // 自动检测浏览器语言
        if (!localStorage.getItem('lang')) {
            var browserLang = navigator.language || navigator.userLanguage;
            if (browserLang.startsWith('en')) this.currentLang = 'en';
            else if (browserLang.startsWith('zh')) this.currentLang = 'zh';
        }
        this.loadTranslations();
        this.updateUI();
    },
    
    // 加载翻译
    loadTranslations: function() {
        this.translations = {
            zh: {
                '聊天室': '聊天室',
                '主页': '主页',
                '进销存': '进销存',
                '智能翻译': '智能翻译',
                '管理后台': '管理后台',
                '登录': '登录',
                '注册': '注册',
                '发送消息': '发送消息...',
                '请输入': '请输入...',
                '确认': '确认',
                '取消': '取消',
                '保存': '保存',
                '删除': '删除',
                '编辑': '编辑',
                '返回': '返回'
            },
            en: {
                '聊天室': 'Chat Room',
                '主页': 'Home',
                '进销存': 'Inventory',
                '智能翻译': 'Translation',
                '管理后台': 'Admin',
                '登录': 'Login',
                '注册': 'Register',
                '发送消息': 'Send message...',
                '请输入': 'Please input...',
                '确认': 'Confirm',
                'cancel': 'Cancel',
                'save': 'Save',
                'delete': 'Delete',
                'edit': 'Edit',
                'back': 'Back'
            }
        };
    },
    
    // 切换语言
    setLang: function(lang) {
        this.currentLang = lang;
        localStorage.setItem('lang', lang);
        this.updateUI();
        // 翻译页面所有文本
        this.translatePage();
    },
    
    // 更新UI语言
    updateUI: function() {
        var lang = this.currentLang;
        // 更新所有带data-i18n属性的元素
        document.querySelectorAll('[data-i18n]').forEach(function(el) {
            var key = el.getAttribute('data-i18n');
            if (Translation.translations[lang] && Translation.translations[lang][key]) {
                el.textContent = Translation.translations[lang][key];
            }
        });
    },
    
    // 翻译页面
    translatePage: function() {
        var lang = this.currentLang;
        var texts = this.translations[lang] || {};
        
        // 替换页面文本
        for (var key in texts) {
            document.body.innerHTML = document.body.innerHTML.replace(new RegExp(key, 'g'), texts[key]);
        }
    },
    
    // 翻译输入框内容
    translateInput: function(inputEl, fromLang, toLang) {
        var text = inputEl.value;
        if (!text) return;
        
        // 调用翻译API
        fetch('/api/translate.php?action=translate', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'text=' + encodeURIComponent(text) + '&from=' + fromLang + '&to=' + toLang
        })
        .then(r => r.json())
        .then(d => {
            if (d.code === 1) {
                inputEl.value = d.data.text;
            }
        });
    },
    
    // 检测语言
    detect: function(text) {
        if (/[\u4e00-\u9fa5]/.test(text)) return 'zh';
        if (/[\u3040-\u309f\u30a0-\u30ff]/.test(text)) return 'ja';
        if (/[\uac00-\ud7af]/.test(text)) return 'ko';
        return 'en';
    }
};

// 自动初始化
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        Translation.init();
    });
} else {
    Translation.init();
}
