<!--
  设计读 (Section 0.B):
    协会官网 for 澳門中醫藥學會 + 国际访客, "传承权威 + 现代专业"语言
    套 A = 古典中医 / 墨朱金 / 思源宋体 / 中药柜抽屉意象

  Design tokens (locked):
    - 墨色:    #1a1a1a (主文字 / 柜体)
    - 朱砂:    #c1272d (主色 / CTA / 药名强调)
    - 烫金:    #b8860b (辅助 / 边框 / 拉手)
    - 宣纸:    #f5ecd9 (浅背景)
    - 药材:    6 个抽屉 (当归/甘草/黄芪/茯苓/陈皮/党参)
    - 留白:    py-24
    - 圆角:    0 (尖锐端庄)

  中药柜意象:
    - 6 个木色抽屉并排
    - 每抽拉手 = 金色圆环
    - 中药名 = 朱砂字竖排 / 楷体
    - hover 抽屉外滑 + 名字放大
    - click 抽屉抽出显示模块入口

  严格遵守:
    - ZERO em-dashes
    - ONE accent color (朱砂红)
    - 砍掉 9 张 emoji 卡片重复 → 药柜抽屉
-->

<template>
  <div class="bg-xuanzhi text-mo text-[15px] leading-relaxed" style="font-family: 'Source Han Serif', 'Noto Serif CJK SC', 'Songti SC', serif;">
    <!-- 1. HERO 全宽 banner + 朱砂蒙版 + 标题浮层 -->
    <section class="relative bg-mo">
      <UiBanner
        position="portal_hero"
        height-class="h-[28rem] md:h-[36rem]"
        :rounded="false"
        :interval="5000"
        placeholder-text="學會活動 · 學術會議"
        placeholder-class="bg-gradient-to-br from-mo via-[#3a1010] to-cinnabar text-gold"
        :manageable="true"
      />
      <div class="absolute inset-0 bg-gradient-to-b from-cinnabar/40 via-mo/30 to-mo/80 pointer-events-none"></div>
      <div class="absolute inset-x-0 bottom-0 pb-12 md:pb-20 pointer-events-none">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-xuanzhi">
          <img src="/logo-wide.png" alt="澳門中醫藥學會" class="h-20 sm:h-24 md:h-28 mb-6 object-contain drop-shadow-[0_0_20px_rgba(184,134,11,0.4)]" />
          <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold leading-[1.1] mb-4 max-w-3xl">
            {{ $t('portal.hero.slogan') }}
          </h1>
          <p class="text-base md:text-lg text-xuanzhi/80 max-w-xl mb-6">
            弘揚岐黃之術 · 濟世利民之心
          </p>
          <div class="flex flex-wrap gap-3 pointer-events-auto">
            <router-link to="/portal/contact" class="px-7 py-3 bg-cinnabar hover:bg-[#a51f24] text-xuanzhi rounded-none font-medium tracking-wider transition">
              加入學會
            </router-link>
            <router-link to="/portal/about" class="px-7 py-3 border border-xuanzhi/40 hover:bg-xuanzhi/10 text-xuanzhi rounded-none font-medium tracking-wider transition">
              了解更多
            </router-link>
          </div>
        </div>
      </div>
      <div class="absolute bottom-0 inset-x-0 h-px bg-gold/40"></div>
    </section>

    <!-- 3. 数据看板 -->
    <section class="py-12 sm:py-16 md:py-24 bg-[#5c3a1e] relative overflow-hidden">
      <div class="absolute inset-0 opacity-5 pointer-events-none" style="background-image: repeating-linear-gradient(90deg, transparent, transparent 2px, rgba(0,0,0,0.3) 2px, rgba(0,0,0,0.3) 3px);"></div>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        <div class="text-center mb-12">
          <!-- 百子柜顶部 LOGO 牌匾 (原图 + 白色背景框) -->
          <div class="flex justify-center mb-8">
            <div class="relative inline-block bg-white px-3 sm:px-5 md:px-6 py-2 sm:py-2.5 md:py-3 shadow-2xl ring-2 ring-gold/40" style="border-radius: 0;">
              <img
                src="/logo-wide-original.jpg"
                alt="澳門中醫藥學會"
                class="h-16 sm:h-20 md:h-24 w-auto"
              />
              <!-- 牌匾装饰: 四角金色钉 -->
              <div class="absolute top-1 left-1 w-1.5 h-1.5 rounded-full bg-gold/70"></div>
              <div class="absolute top-1 right-1 w-1.5 h-1.5 rounded-full bg-gold/70"></div>
              <div class="absolute bottom-1 left-1 w-1.5 h-1.5 rounded-full bg-gold/70"></div>
              <div class="absolute bottom-1 right-1 w-1.5 h-1.5 rounded-full bg-gold/70"></div>
            </div>
          </div>
          <div class="text-[11px] tracking-[0.32em] uppercase text-gold mb-3 font-mono">百子櫃</div>
          <h2 class="text-3xl md:text-4xl font-bold text-xuanzhi">探索學會</h2>
          <p class="text-xuanzhi/60 mt-3 text-sm">六味本草 · 各歸其經 · 點擊抽屜探入學會</p>
        </div>

        <div class="relative mx-auto max-w-6xl">
          <div class="h-6 bg-gradient-to-b from-[#3a2410] to-[#5c3a1e] border-t-2 border-gold/40 relative">
            <div class="absolute inset-x-0 top-1 flex justify-around">
              <div v-for="n in 6" :key="n" class="w-1 h-3 bg-gold/40 rounded-b"></div>
            </div>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 md:gap-4 bg-[#3a2410] p-4 md:p-5 border-2 border-gold/30 shadow-2xl">
            <div
              v-for="(herb, i) in herbs"
              :key="herb.key"
              class="flex flex-col"
            >
              <!-- 牌匾 (药名 + 功能名) - 镜匾式 -->
              <div
                class="relative px-1 py-2 mb-2 text-center border-2 transition-all duration-500"
                :class="openIndex === i ? 'bg-gold border-gold shadow-lg' : 'bg-[#2a1808] border-gold/60 hover:border-gold'"
                style="border-radius: 0;"
              >
                <div
                  class="hero-name text-[15px] md:text-base font-bold leading-tight"
                  :class="openIndex === i ? 'text-mo' : 'text-gold'"
                  style="font-family: 'KaiTi', 'STKaiti', 'DFKai-SB', 'BiauKai', cursive; letter-spacing: 0.1em;"
                >
                  {{ herb.label }}
                </div>
                <div
                  class="text-[10px] md:text-[11px] font-bold leading-tight mt-0.5"
                  :class="openIndex === i ? 'text-mo/80' : 'text-xuanzhi/90'"
                  style="font-family: 'KaiTi', 'STKaiti', 'DFKai-SB', 'BiauKai', cursive; letter-spacing: 0.05em;"
                >
                  {{ herb.module }}
                </div>
                <!-- 牌匾装饰: 上下小金钉 -->
                <div class="absolute top-0.5 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-gold/60"></div>
                <div class="absolute bottom-0.5 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-gold/60"></div>
              </div>

              <!-- 抽屉本体 -->
              <button
                @click="toggleDrawer(i)"
                class="group relative aspect-[3/4] w-full bg-gradient-to-br from-[#7a4a28] via-[#5c3a1e] to-[#3a2410] border-2 border-[#8a5a32] hover:border-gold transition-all duration-500 cursor-pointer overflow-hidden focus:outline-none focus:ring-2 focus:ring-gold/50"
                :class="[
                  openIndex === i ? 'drawer-open' : '',
                  openIndex !== null && openIndex !== i ? 'drawer-closed' : ''
                ]"
                :aria-label="`${herb.label} - ${herb.module}`"
              >
                <!-- 拉手: 圆环 -->
                <div class="absolute left-1/2 top-3 -translate-x-1/2 w-5 h-5 rounded-full bg-gradient-to-br from-gold to-[#8a6420] border-2 border-[#5a3a10] shadow-md z-20 group-hover:scale-110 transition-transform"></div>

                <!-- 合上态: 楷体大字 功能名 + 药名拼音 (避免与牌匾重复) -->
                <div class="absolute inset-0 transition-all duration-500 pt-12" :class="openIndex === i ? 'opacity-0' : 'opacity-100'">
                  <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-center w-full px-1">
                    <div class="hero-name text-xuanzhi text-xl md:text-2xl font-bold leading-tight" style="font-family: 'KaiTi', 'STKaiti', 'DFKai-SB', 'BiauKai', cursive; text-shadow: 0 1px 3px rgba(0,0,0,0.7); letter-spacing: 0.15em;">
                      {{ herb.module }}
                    </div>
                    <div class="text-[10px] text-gold/70 font-mono tracking-wider mt-2">{{ herb.label }} · {{ herb.pinyin }}</div>
                  </div>
                </div>

                <!-- 打开态: 中心 药名 + 功效 + 按钮 -->
                <div class="absolute inset-0 bg-gradient-to-br from-[#f5ecd9] to-[#e8d8b8] transition-all duration-500 flex flex-col items-center justify-center p-3 text-center" :class="openIndex === i ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4 pointer-events-none'">
                  <div class="hero-name text-2xl md:text-3xl font-bold text-cinnabar mb-1" style="font-family: 'KaiTi', 'STKaiti', 'DFKai-SB', 'BiauKai', cursive; letter-spacing: 0.1em;">
                    {{ herb.label }}
                  </div>
                  <div class="text-[10px] text-mo/50 font-mono mb-2 tracking-wider">{{ herb.pinyin }}</div>
                  <div class="text-[10px] text-mo/60 leading-tight mb-2 px-1">{{ herb.effect }}</div>
                  <router-link :to="herb.to" class="px-2.5 py-1 bg-cinnabar text-xuanzhi text-[10px] tracking-wider hover:bg-[#a51f24] transition rounded-none">
                    進入 →
                  </router-link>
                </div>

                <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-gold/30 to-transparent"></div>
                <div class="absolute bottom-0 inset-x-0 h-2 bg-gradient-to-t from-black/40 to-transparent"></div>
              </button>
            </div>
          </div>

          <div class="h-4 bg-gradient-to-b from-[#3a2410] to-[#2a1808] border-b-2 border-gold/30"></div>

          <div class="text-center mt-6 text-xuanzhi/50 text-xs font-mono">
            <span v-if="openIndex === null">點擊任一抽屜 · 抽開探入</span>
            <span v-else>再次點擊 · 合上抽屜</span>
          </div>
        </div>
      </div>
    </section>
    <section class="py-16 bg-xuanzhi border-b border-gold/20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 md:gap-12">
          <div v-for="(stat, i) in stats" :key="i" class="text-center">
            <div class="text-4xl md:text-5xl font-bold text-cinnabar mb-2 font-mono">
              {{ stat.value }}
            </div>
            <div class="text-xs tracking-[0.18em] uppercase text-mo/60">
              {{ stat.label }}
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 4. 关于学会 -->
    <section v-if="info" class="py-24 bg-xuanzhi">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
          <div class="lg:col-span-4 flex items-center gap-4">
            <div class="w-20 h-20 sm:w-24 sm:h-24 md:w-28 md:h-28 rounded-full bg-xuanzhi/50 backdrop-blur p-2 ring-2 ring-gold ring-offset-2 ring-offset-xuanzhi shadow-xl shrink-0"><img src="/logo-square.png" alt="澳門中醫藥學會" class="w-full h-full object-contain" /></div>
            <div class="text-2xl font-bold leading-none">澳門中醫藥學會</div>
            <div class="text-sm text-mo/60 mb-4 tracking-wider leading-tight">Associação dos Investigadores, Praticantes e Promotores da Medicina Chinesa de Macau</div>
            <div class="flex items-center gap-4 text-sm text-mo/70">
              <span v-if="info.founded_year"><span class="text-cinnabar font-mono">{{ info.founded_year }}</span> 年成立</span>
              <span v-if="info.member_count" class="border-l border-gold/30 pl-4"><span class="text-cinnabar font-mono">{{ info.member_count }}</span> 名會員</span>
            </div>
          </div>
          <div class="lg:col-span-8 space-y-8">
            <div>
              <div class="text-[11px] tracking-[0.32em] uppercase text-cinnabar mb-3 font-mono">Mission</div>
              <h2 class="text-3xl font-bold mb-4 leading-snug">{{ $t('association.bio_short') }}</h2>
              <p class="text-base text-mo/80 leading-loose">{{ info.intro || '-' }}</p>
            </div>
            <div v-if="info.vision">
              <div class="text-[11px] tracking-[0.32em] uppercase text-cinnabar mb-3 font-mono">Vision</div>
              <p class="text-base text-mo/80 leading-loose italic">{{ info.vision }}</p>
            </div>
            <div v-if="timeline.length">
              <div class="text-[11px] tracking-[0.32em] uppercase text-cinnabar mb-3 font-mono">Milestones</div>
              <ol class="border-l-2 border-gold/40 pl-6 space-y-4">
                <li v-for="m in timeline" :key="m.year" class="relative">
                  <div class="absolute -left-[31px] top-1 w-3 h-3 rounded-full bg-cinnabar"></div>
                  <span class="font-mono text-cinnabar font-bold mr-3">{{ m.year }}</span>
                  <span class="text-mo/80">{{ m.text }}</span>
                </li>
              </ol>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. ★ 百子柜 (核心特色, 移到 Hero 后) -->

    <!-- 6. 最新公告 -->
    <section v-if="latestNews.length" class="py-24 bg-xuanzhi">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-10">
          <div>
            <div class="text-[11px] tracking-[0.32em] uppercase text-cinnabar mb-3 font-mono">Latest</div>
            <h2 class="text-3xl font-bold">{{ $t('portal.sections.latest_news') }}</h2>
          </div>
          <router-link to="/portal/news" class="text-sm text-cinnabar hover:underline flex items-center gap-1">
            查看更多 <span class="material-symbols-outlined text-base">arrow_forward</span>
          </router-link>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <article v-if="latestNews[0]" class="group cursor-pointer lg:row-span-2" @click="$router.push(`/portal/news/${latestNews[0].id}`)">
            <div class="aspect-[4/3] overflow-hidden bg-gradient-to-br from-[#3a1010] to-mo mb-4">
              <img v-if="latestNews[0].cover_image" :src="latestNews[0].cover_image" :alt="latestNews[0].title" class="w-full h-full object-cover group-hover:scale-105 transition duration-700" loading="lazy" />
              <div v-else class="w-full h-full flex items-center justify-center">
                <span class="material-symbols-outlined text-gold/30 text-7xl">article</span>
              </div>
            </div>
            <div class="text-xs text-mo/50 mb-2 font-mono">{{ formatDate(latestNews[0].published_at) }}</div>
            <h3 class="text-2xl font-bold mb-3 group-hover:text-cinnabar transition leading-snug">{{ latestNews[0].title }}</h3>
            <p class="text-base text-mo/70 leading-relaxed line-clamp-3">{{ latestNews[0].summary }}</p>
          </article>
          <div class="space-y-5">
            <article v-for="n in latestNews.slice(1, 5)" :key="n.id" class="group cursor-pointer border-b border-gold/20 pb-5 last:border-b-0" @click="$router.push(`/portal/news/${n.id}`)">
              <div class="flex gap-5">
                <div class="flex-1">
                  <div class="text-xs text-mo/50 mb-1 font-mono">{{ formatDate(n.published_at) }}</div>
                  <h3 class="font-bold mb-1 group-hover:text-cinnabar transition line-clamp-2">{{ n.title }}</h3>
                  <p class="text-sm text-mo/60 line-clamp-1">{{ n.summary }}</p>
                </div>
              </div>
            </article>
          </div>
        </div>
      </div>
    </section>

    <!-- 7. 近期活动 -->
    <section v-if="upcomingActivities.length" class="py-24 bg-[#3a1010] text-xuanzhi">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-10">
          <div>
            <div class="text-[11px] tracking-[0.32em] uppercase text-gold mb-3 font-mono">Upcoming</div>
            <h2 class="text-3xl font-bold">{{ $t('portal.sections.upcoming_activities') }}</h2>
          </div>
          <router-link to="/portal/activities" class="text-sm text-gold hover:underline flex items-center gap-1">
            活動回顧 <span class="material-symbols-outlined text-base">arrow_forward</span>
          </router-link>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <article v-for="a in upcomingActivities" :key="a.id" class="group cursor-pointer bg-xuanzhi/5 hover:bg-xuanzhi/10 border border-gold/20 hover:border-gold transition" @click="$router.push(`/portal/activities/${a.id}`)">
            <div class="aspect-[16/9] overflow-hidden bg-gradient-to-br from-mo to-[#3a1010]">
              <img v-if="a.cover_image" :src="a.cover_image" :alt="a.title" class="w-full h-full object-cover group-hover:scale-105 transition duration-700" loading="lazy" />
              <div v-else class="w-full h-full flex items-center justify-center">
                <span class="material-symbols-outlined text-gold/30 text-6xl">event</span>
              </div>
            </div>
            <div class="p-6">
              <div class="text-xs text-gold mb-2 font-mono">{{ formatDate(a.start_time) }} · {{ a.location }}</div>
              <h3 class="text-lg font-bold mb-3 group-hover:text-gold transition leading-snug">{{ a.title }}</h3>
              <div class="flex justify-between items-center pt-3 border-t border-gold/20 text-xs">
                <span class="text-xuanzhi/60">限 {{ a.max_participants || '∞' }} 人 · 已報 {{ a.current_participants }}</span>
                <span class="text-cinnabar font-bold">{{ a.fee > 0 ? 'MOP ' + a.fee : '免費' }}</span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- 8. 学术前沿 + 期刊精选 -->
    <section class="py-12 sm:py-16 md:py-24 bg-xuanzhi">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
          <div v-if="latestAcademic.length">
            <div class="flex items-end justify-between mb-6 pb-4 border-b border-gold/30">
              <div>
                <div class="text-[11px] tracking-[0.32em] uppercase text-cinnabar mb-2 font-mono">Research</div>
                <h2 class="text-2xl font-bold">{{ $t('portal.sections.latest_academic') }}</h2>
              </div>
              <router-link to="/portal/academic" class="text-xs text-cinnabar hover:underline">查看更多</router-link>
            </div>
            <ol class="space-y-5">
              <li v-for="(a, i) in latestAcademic" :key="a.id" class="flex gap-4 cursor-pointer group" @click="$router.push(`/portal/academic/${a.id}`)">
                <span class="text-2xl font-mono font-bold text-cinnabar/40 group-hover:text-cinnabar transition">{{ String(i + 1).padStart(2, '0') }}</span>
                <div class="flex-1 border-b border-gold/10 pb-4">
                  <h3 class="font-bold mb-1 group-hover:text-cinnabar transition line-clamp-2">{{ a.title }}</h3>
                  <p class="text-xs text-mo/50 font-mono">
                    <span v-if="a.author_name">{{ a.author_name }}</span>
                    <span v-if="a.journal_name"> · {{ a.journal_name }}</span>
                    <span v-if="a.doi"> · DOI {{ a.doi }}</span>
                  </p>
                </div>
              </li>
            </ol>
          </div>
          <div v-if="featuredJournals.length">
            <div class="flex items-end justify-between mb-6 pb-4 border-b border-gold/30">
              <div>
                <div class="text-[11px] tracking-[0.32em] uppercase text-cinnabar mb-2 font-mono">Journals</div>
                <h2 class="text-2xl font-bold">{{ $t('portal.sections.featured_journals') }}</h2>
              </div>
              <router-link to="/portal/journals" class="text-xs text-cinnabar hover:underline">查看更多</router-link>
            </div>
            <div class="grid grid-cols-2 gap-5">
              <article v-for="j in featuredJournals" :key="j.id" class="group cursor-pointer" @click="$router.push(`/portal/journals/${j.id}`)">
                <div class="aspect-[3/4] bg-gradient-to-br from-[#f5ecd9] to-[#e8d8b8] mb-3 overflow-hidden">
                  <img v-if="j.cover_image" :src="j.cover_image" class="w-full h-full object-cover group-hover:scale-105 transition duration-700" />
                  <div v-else class="w-full h-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-mo/20 text-5xl">menu_book</span>
                  </div>
                </div>
                <div class="text-[10px] text-cinnabar mb-1 font-mono tracking-wider">VOL.{{ j.volume }} NO.{{ j.issue }}</div>
                <h3 class="font-bold text-sm leading-snug group-hover:text-cinnabar transition line-clamp-2">{{ j.title }}</h3>
              </article>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 9. 联系我们 -->
    <section id="contact" class="py-24 bg-mo text-xuanzhi border-t border-gold/30">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <div class="text-[11px] tracking-[0.32em] uppercase text-gold mb-3 font-mono">Contact</div>
          <h2 class="text-3xl md:text-4xl font-bold mb-3">{{ $t('portal.contact.title') }}</h2>
          <div class="w-12 h-px bg-gold mx-auto"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-10">
          <div v-if="info.phone" class="text-center border border-gold/20 p-8 hover:border-gold transition">
            <div class="text-gold text-4xl mb-3 material-symbols-outlined">call</div>
            <div class="text-xs tracking-[0.18em] uppercase text-xuanzhi/50 mb-2">{{ $t('portal.footer.phone') }}</div>
            <div class="font-mono text-base">{{ info.phone }}</div>
          </div>
          <div v-if="info.email" class="text-center border border-gold/20 p-8 hover:border-gold transition">
            <div class="text-gold text-4xl mb-3 material-symbols-outlined">mail</div>
            <div class="text-xs tracking-[0.18em] uppercase text-xuanzhi/50 mb-2">{{ $t('portal.footer.email') }}</div>
            <div class="font-mono text-base">{{ info.email }}</div>
          </div>
          <div v-if="info.address" class="text-center border border-gold/20 p-8 hover:border-gold transition">
            <div class="text-gold text-4xl mb-3 material-symbols-outlined">location_on</div>
            <div class="text-xs tracking-[0.18em] uppercase text-xuanzhi/50 mb-2">{{ $t('portal.footer.address') }}</div>
            <div class="font-mono text-base">{{ info.address }}</div>
          </div>
        </div>
        <div class="text-center">
          <router-link to="/portal/contact" class="inline-block px-10 py-3 bg-cinnabar hover:bg-[#a51f24] text-xuanzhi font-medium tracking-wider transition">
            提交留言
          </router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import UiBanner from '@/components/UiBanner.vue'

const info = ref({})
const latestNews = ref([])
const upcomingActivities = ref([])
const latestAcademic = ref([])
const featuredJournals = ref([])

const herbs = [
  { key: 'news',       label: '當歸', pinyin: 'DANG GUI',  effect: '補血活血 · 調經止痛', module: '公告資訊',      to: '/portal/news' },
  { key: 'activities', label: '甘草', pinyin: 'GAN CAO',   effect: '補脾益氣 · 調和諸藥', module: '活動報名',     to: '/portal/activities' },
  { key: 'academic',   label: '黃芪', pinyin: 'HUANG QI',  effect: '補氣固表 · 利尿托毒', module: '學術前沿',     to: '/portal/academic' },
  { key: 'members',    label: '茯苓', pinyin: 'FU LING',   effect: '利水滲濕 · 健脾寧心', module: '會員風采',     to: '/portal/members' },
  { key: 'downloads',  label: '陳皮', pinyin: 'CHEN PI',   effect: '理氣健脾 · 燥濕化痰', module: '資料下載',     to: '/portal/downloads' },
  { key: 'contact',    label: '黨參', pinyin: 'DANG SHEN', effect: '健脾益肺 · 養血生津', module: '聯繫我們',     to: '/portal/contact' },
]

const openIndex = ref(null)

function toggleDrawer(i) {
  openIndex.value = openIndex.value === i ? null : i
}

const stats = ref([
  { value: '32+', label: 'Years Founded' },
  { value: '800+', label: 'Members' },
  { value: '120+', label: 'Activities' },
  { value: '28', label: 'Journals / Yr' },
])

const timeline = [
  { year: 1991, text: '學會正式成立, 創會會員 47 人' },
  { year: 2003, text: '舉辦首屆澳門中醫藥國際學術研討會' },
  { year: 2014, text: '獲特區政府頒授社會服務傑出獎' },
  { year: 2021, text: '中醫藥文化列入澳門非物質文化遺產名錄' },
]

function formatDate(t) {
  if (!t) return ''
  return new Date(t).toLocaleDateString('zh-TW')
}

onMounted(async () => {
  const { api } = await import('@/services/api.js')

  try {
    const r = await api.get('/association/info', { params: { server_profile_id: 7 } })
    if (r.code === 0) info.value = r.data || {}
  } catch (e) {}

  try {
    const r = await api.get('/association/announcements', { params: { server_profile_id: 7, size: 5, status: 'published' } })
    if (r.code === 0) latestNews.value = (Array.isArray(r.data) ? r.data : (r.data.list || []))
  } catch (e) {}

  try {
    const r = await api.get('/association/activities', { params: { server_profile_id: 7, size: 3 } })
    if (r.code === 0) upcomingActivities.value = (Array.isArray(r.data) ? r.data : (r.data.list || []))
  } catch (e) {}

  try {
    const r = await api.get('/association/academic', { params: { size: 4 } })
    if (r.code === 0) latestAcademic.value = (Array.isArray(r.data) ? r.data : (r.data.list || []))
  } catch (e) {}

  try {
    const r = await api.get('/association/journals', { params: { size: 4 } })
    if (r.code === 0) featuredJournals.value = (Array.isArray(r.data) ? r.data : (r.data.list || []))
  } catch (e) {}
})
</script>

<style scoped>
.bg-xuanzhi { background-color: #f5ecd9; }
.bg-mo { background-color: #1a1a1a; }
.bg-cinnabar { background-color: #c1272d; }
.text-xuanzhi { color: #f5ecd9; }
.text-mo { color: #1a1a1a; }
.text-cinnabar { color: #c1272d; }
.text-gold { color: #b8860b; }
.border-gold\/30 { border-color: rgb(184 134 11 / 0.3); }
.border-gold\/20 { border-color: rgb(184 134 11 / 0.2); }
.border-gold\/40 { border-color: rgb(184 134 11 / 0.4); }

.drawer-open {
  transform: translateY(-8px);
  box-shadow: 0 20px 40px rgba(0,0,0,0.5), 0 0 0 2px #b8860b;
}

.drawer-closed {
  opacity: 0.6;
  transform: scale(0.96);
}

button:hover {
  transform: translateY(-4px);
}

.writing-vertical {
  display: inline-block;
  white-space: nowrap;
}
</style>