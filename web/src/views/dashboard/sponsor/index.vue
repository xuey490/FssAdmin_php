<template>
  <div class="sponsor-page">
    <div class="art-card p-6 max-sm:p-4 sponsor-page__card">
      <div class="sponsor-page__header">
        <h2 class="sponsor-page__title">赞助方式</h2>
        <p class="sponsor-page__desc">
		  If this project has been helpful to you, donations are welcome! Your support will be used to purchase tools like ChatGPT, Copilot,Claude Code, Cursor, etc., to improve development efficiency and make the project even better. Thank you for your encouragement and support!<br />
		  如果这个项目对你有所帮助，欢迎捐赠支持！你的支持将用于订阅ChatGPT、Copilot、Claude Code、Cursor 等工具，以提升开发效率，让项目变得更好。感谢你的鼓励与支持！<br />
		  If your sponsorship exceeds ¥200, please contact the author via email: xuey863toy@gmail.com. Full source code will be provided.<br />
          赞助金额超过￥200元，请添加作者微信号：<span @click="copyInstanceNo"><b>xdbyvibm6</b></span> ,将提供提供全套源码！
        </p>
      </div>

      <el-row :gutter="20" class="sponsor-page__qr-row">
        <el-col :lg="12" :md="12" :sm="24" :xs="24" v-for="item in qrItems" :key="item.key">
          <div class="sponsor-card" :class="`sponsor-card--${item.key}`">
            <h3 class="sponsor-card__title">{{ item.title }}</h3>
            <div class="sponsor-card__image-wrap">
              <el-image :src="item.src" fit="contain" class="sponsor-card__image">
                <template #error>
                  <div class="sponsor-card__placeholder">
                    <span>{{ item.placeholder }}</span>
                  </div>
                </template>
              </el-image>
            </div>
            <p class="sponsor-card__hint">{{ item.hint }}</p>
          </div>
        </el-col>
      </el-row>

      <div class="sponsor-page__section">
        <p class="sponsor-page__sub-title">您的赞助将用于：</p>
        <ul class="sponsor-page__list">
          <li>项目基础设施投入（服务器、域名、带宽等）。</li>
		  <li>订阅ChatGPT、Copilot、Claude Code、Cursor等工具</li>
          <li>支持开发者持续维护与新功能开发。</li>
          <li>...</li>
        </ul>
      </div>

      <div class="sponsor-page__section">
        <h3 class="sponsor-page__table-title">您的名字将会永远记录在项目的赞助表格上</h3>
        <div class="sponsor-page__table-wrap">
          <el-table :data="sponsorRecords" border stripe class="sponsor-page__table">
            <el-table-column prop="source" label="来源" width="120" />
            <el-table-column prop="name" label="昵称" min-width="160" />
            <el-table-column prop="amount" label="金额" width="150" />
            <el-table-column prop="time" label="时间" min-width="180" />
          </el-table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  defineOptions({ name: 'Sponsor' })
  import { ElMessage } from 'element-plus'  

  type QrItem = {
    key: 'alipay' | 'wechat'
    title: string
    src: string
    hint: string
    placeholder: string
  }
  
  const copyInstanceNo = async () => {
    const no = 'xdbyvibm6'.trim()
    if (!no) return
    try {
      await navigator.clipboard.writeText(no)
      ElMessage.success('微信号已复制')
    } catch {
      ElMessage.warning('复制失败，请手动复制')
    }
  }
  
  const qrItems = ref<QrItem[]>([
    {
      key: 'alipay',
      title: '推荐使用支付宝',
      src: new URL('/src/assets/images/qrcode/alipay.png', import.meta.url).href,
      hint: '打开支付宝扫一扫',
      placeholder: '请放置支付宝二维码'
    },
    {
      key: 'wechat',
      title: '推荐使用微信支付',
      src: new URL('/src/assets/images/qrcode/wechat.png', import.meta.url).href,
      hint: '打开微信扫一扫',
      placeholder: '请放置微信二维码'
    }
  ])

  const sponsorRecords = ref([

  ])
</script>

<style scoped lang="scss">
  .sponsor-page {
    padding: 0;
  }

  .sponsor-page__card {
    padding: 22px !important;
  }

  .sponsor-page__header {
    margin-bottom: 26px;
    padding-bottom: 14px;
    border-bottom: 1px solid #e5e7eb;
  }

  .sponsor-page__title {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: #1f2d3d;
  }

  .sponsor-page__desc {
    margin: 12px 0 0;
    font-size: 15px;
    color: #5e6d82;
    line-height: 1.75;
  }

  .sponsor-page__qr-row {
    margin-top: 8px;
  }

  .sponsor-card {
    border-radius: 14px;
    padding: 18px 16px 16px;
    margin-bottom: 18px;
    color: #fff;
    text-align: center;
    min-height: 360px;
    overflow: hidden;
  }

  .sponsor-card--alipay {
    background: linear-gradient(135deg, #2a8cff, #0c66e4);
  }

  .sponsor-card--wechat {
    background: linear-gradient(135deg, #1fbf5b, #0e9f44);
  }

  .sponsor-card__title {
    margin: 0 0 14px;
    font-size: 32px;
    font-weight: 700;
    line-height: 1.25;
    word-break: break-word;
  }

  .sponsor-card__image-wrap {
    margin: 0 auto;
    width: min(250px, 100%);
    aspect-ratio: 1 / 1;
    background: #fff;
    border-radius: 10px;
    padding: 8px;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .sponsor-card__image {
    width: 100%;
    height: 100%;
    border-radius: 6px;
  }

  .sponsor-card__placeholder {
    width: 100%;
    height: 100%;
    border: 1px dashed #d0d5dd;
    border-radius: 6px;
    color: #475467;
    font-size: 12px;
    line-height: 1.6;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px;
    text-align: center;
    word-break: break-all;
    box-sizing: border-box;
    background: #f8fafc;
  }

  .sponsor-card__hint {
    margin: 14px 0 0;
    font-size: 17px;
    font-weight: 500;
  }

  .sponsor-page__section {
    margin-top: 24px;
  }

  .sponsor-page__sub-title {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    color: #1f2d3d;
  }

  .sponsor-page__list {
    margin: 14px 0 0;
    padding-left: 22px;
    color: #364152;
    line-height: 1.9;
  }

  .sponsor-page__table-title {
    margin: 0 0 14px;
    font-size: 24px;
    font-weight: 700;
    color: #1f2d3d;
  }

  .sponsor-page__table {
    margin-top: 10px;
    min-width: 640px;
  }

  .sponsor-page__table-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  @media screen and (max-width: 768px) {
    .sponsor-page {
      padding: 0;
    }

    .sponsor-page__card {
      padding: 16px !important;
    }

    .sponsor-page__header {
      margin-bottom: 18px;
      padding-bottom: 12px;
    }

    .sponsor-page__title {
      font-size: 24px;
    }

    .sponsor-card {
      min-height: auto;
    }

    .sponsor-card__title {
      font-size: 22px;
    }

    .sponsor-card__image-wrap {
      width: min(210px, 100%);
    }

    .sponsor-page__table-title {
      font-size: 24px;
    }

    .sponsor-page__section {
      margin-top: 18px;
    }

    .sponsor-page__table {
      min-width: 560px;
    }
  }
</style>
