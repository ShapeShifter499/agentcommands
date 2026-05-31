import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
  agentcommands: 'src/main.js',
}, {
  assetsPrefix: '',
  thirdPartyLicense: false,
})
