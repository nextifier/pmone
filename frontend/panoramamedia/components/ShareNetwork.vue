<script setup lang="ts">
import { computed, ref } from "vue";
const useNetworks = {
  baidu: "http://cang.baidu.com/do/add?iu=@u&it=@t",
  buffer: "https://bufferapp.com/add?text=@t&url=@u",
  email: "mailto:?subject=@t&body=@u%0D%0A@d",
  evernote: "https://www.evernote.com/clip.action?url=@u&title=@t",
  facebook:
    "https://www.facebook.com/sharer/sharer.php?u=@u&title=@t&description=@d&quote=@q&hashtag=@h",
  flipboard:
    "https://share.flipboard.com/bookmarklet/popout?v=2&url=@u&title=@t",
  hackernews: "https://blog.ycombinator.com/submitlink?u=@u&t=@t",
  instapaper: "http://www.instapaper.com/edit?url=@u&title=@t&description=@d",
  line: "http://line.me/R/msg/text/?@t%0D%0A@u%0D%0A@d",
  linkedin: "https://www.linkedin.com/sharing/share-offsite/?url=@u",
  messenger: "fb-messenger://share/?link=@u",
  odnoklassniki:
    "https://connect.ok.ru/dk?st.cmd=WidgetSharePreview&st.shareUrl=@u&st.comments=@t",
  pinterest:
    "https://pinterest.com/pin/create/button/?url=@u&media=@m&description=@t",
  pocket: "https://getpocket.com/save?url=@u&title=@t",
  quora: "https://www.quora.com/share?url=@u&title=@t",
  reddit: "https://www.reddit.com/submit?url=@u&title=@t",
  skype: "https://web.skype.com/share?url=@t%0D%0A@u%0D%0A@d",
  sms: "sms:?body=@t%0D%0A@u%0D%0A@d",
  stumbleupon: "https://www.stumbleupon.com/submit?url=@u&title=@t",
  telegram: "https://t.me/share/url?url=@u&text=@t%0D%0A@d",
  tumblr: "https://www.tumblr.com/share/link?url=@u&name=@t&description=@d",
  x: "https://x.com/intent/tweet?text=@t&url=@u&hashtags=@h@tu",
  viber: "viber://forward?text=@t%0D%0A@u%0D%0A@d",
  vk: "https://vk.com/share.php?url=@u&title=@t&description=@d&image=@m&noparse=true",
  weibo: "http://service.weibo.com/share/share.php?url=@u&title=@t&pic=@m",
  whatsapp: "https://api.whatsapp.com/send?text=@t%0D%0A@u%0D%0A@d",
  wordpress: "https://wordpress.com/press-this.php?u=@u&t=@t&s=@d&i=@m",
  xing: "https://www.xing.com/social/share/spi?op=share&url=@u&title=@t",
  yammer:
    "https://www.yammer.com/messages/new?login=true&status=@t%0D%0A@u%0D%0A@d",
};
let $window = typeof window !== "undefined" ? window : null;
const popupTop = ref(0);
const popupLeft = ref(0);
const popupInterval = ref(null);
const emit = defineEmits(["open:network", "close:network", "change:network"]);
const props = defineProps({
  /**
   * Name of the network to display.
   */
  network: {
    type: String,
    required: true,
  },

  /**
   * URL of the content to share.
   */
  url: {
    type: String,
    required: true,
  },

  /**
   * Title of the content to share.
   */
  title: {
    type: String,
    required: true,
  },

  /**
   * Description of the content to share.
   */
  description: {
    type: String,
    default: "",
  },

  /**
   * Quote content, used for Facebook.
   */
  quote: {
    type: String,
    default: "",
  },

  /**
   * Hashtags, used for Twitter and Facebook.
   */
  hashtags: {
    type: String,
    default: "",
  },

  /**
   * Twitter user, used for Twitter
   * @var string
   */
  twitterUser: {
    type: String,
    default: "",
  },

  /**
   * Media to share, used for Pinterest
   */
  media: {
    type: String,
    default: "",
  },

  /**
   * Properties to configure the popup window.
   */
  popup: {
    type: Object,
    default: () => ({
      width: 800,
      height: 720,
    }),
  },
});

/**
 * List of available networks
 */
const networks = computed(() => {
  return useNetworks;
});

/**
 * Formatted network name.
 */
const key = computed(() => {
  return props.network.toLowerCase();
});

/**
 * Network sharing raw sharing link.
 */
const rawLink = computed(() => {
  const ua = navigator.userAgent.toLowerCase();

  /**
   * On IOS, SMS sharing link need a special formatting
   * Source: https://weblog.west-wind.com/posts/2013/Oct/09/Prefilling-an-SMS-on-Mobile-Devices-with-the-sms-Uri-Scheme#Body-only
   */
  if (
    key.value === "sms" &&
    (ua.indexOf("iphone") > -1 || ua.indexOf("ipad") > -1)
  ) {
    return networks.value[key.value].replace(":?", ":&");
  }
  return networks.value[key.value];
});

/**
 * Create the url for sharing.
 */
const shareLink = computed(() => {
  let link = rawLink.value;
  /**
   * Twitter sharing shouldn't include empty parameter
   * Source: https://github.com/nicolasbeauvais/vue-social-sharing/issues/143
   */
  if (key.value === "x") {
    if (!props.hashtags.length) link = link.replace("&hashtags=@h", "");
    if (!props.twitterUser.length) link = link.replace("@tu", "");
  }
  return link
    .replace(/@tu/g, "&via=" + encodeURIComponent(props.twitterUser))
    .replace(/@u/g, encodeURIComponent(props.url))
    .replace(/@t/g, encodeURIComponent(props.title))
    .replace(/@d/g, encodeURIComponent(props.description))
    .replace(/@q/g, encodeURIComponent(props.quote))
    .replace(/@h/g, encodedHashtags.value)
    .replace(/@m/g, encodeURIComponent(props.media));
});

/**
 * Encoded hashtags for the current social network.
 */
const encodedHashtags = computed(() => {
  if (key.value === "facebook" && props.hashtags.length) {
    return "%23" + props.hashtags.split(",")[0];
  }
  return props.hashtags;
});

/**
 * Center the popup on multi-screens
 * http://stackoverflow.com/questions/4068373/center-a-popup-window-on-screen/32261263
 */
const resizePopup = () => {
  const width =
    $window.innerWidth ||
    document.documentElement.clientWidth ||
    $window.screenX;
  const height =
    $window.innerHeight ||
    document.documentElement.clientHeight ||
    $window.screenY;
  const systemZoom = width / $window.screen.availWidth;
  popupLeft.value =
    (width - props.popup.width) / 2 / systemZoom +
    ($window.screenLeft !== undefined ? $window.screenLeft : $window.screenX);
  popupTop.value =
    (height - props.popup.height) / 2 / systemZoom +
    ($window.screenTop !== undefined ? $window.screenTop : $window.screenY);
};

/**
 * Shares URL in specified network.
 */
const share = () => {
  resizePopup();
  let popupWindow;
  // If a popup window already exist, we close it and trigger a change event.
  if (popupWindow && popupInterval.value) {
    clearInterval(popupInterval.value);
    // Force close (for Facebook)
    popupWindow.close();
    doEmit("change");
  }
  popupWindow = $window.open(
    shareLink.value,
    "sharer-" + key.value,
    ",height=" +
      props.popup.height +
      ",width=" +
      props.popup.width +
      ",left=" +
      popupLeft.value +
      ",top=" +
      popupTop.value +
      ",screenX=" +
      popupLeft.value +
      ",screenY=" +
      popupTop.value,
  );
  // If popup are prevented (AdBlocker, Mobile App context..), popup.window stays undefined and we can't display it
  if (!popupWindow) return;
  popupWindow.focus();
  // Create an interval to detect popup closing event
  popupInterval.value = setInterval(() => {
    if (!popupWindow || popupWindow.closed) {
      clearInterval(popupInterval.value);
      popupWindow = null;
      doEmit("close");
    }
  }, 500);
  doEmit("open");
};

/**
 * Touches network and emits click event.
 */
const touch = () => {
  window.open(shareLink.value, "_blank");
  doEmit("open");
};

const doEmit = (action) => {
  emit(`${action}:network`, {
    network: key.value,
    share_url: props.url,
  });
};
</script>
<template>
  <button
    :class="`share-network-${key}`"
    @click="rawLink.substring(0, 4) === 'http' ? share() : touch()"
  >
    <slot />
  </button>
</template>
