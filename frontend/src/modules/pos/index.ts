import { lazyPage, type FrontendModule } from '@/app/routes'

const PosDashboardPage = lazyPage(() => import('./pages/PosDashboardPage'), 'PosDashboardPage')
const RegisterPage = lazyPage(() => import('./register/RegisterPage'), 'RegisterPage')
const PosOrdersPage = lazyPage(() => import('./pages/PosOrdersPage'), 'PosOrdersPage')
const PosOrderPage = lazyPage(() => import('./pages/PosOrderPage'), 'PosOrderPage')
const PosSessionsPage = lazyPage(() => import('./pages/PosSessionsPage'), 'PosSessionsPage')
const PosSessionPage = lazyPage(() => import('./pages/PosSessionPage'), 'PosSessionPage')
const PosConfigsPage = lazyPage(() => import('./pages/PosConfigsPage'), 'PosConfigsPage')
const PaymentMethodsPage = lazyPage(() => import('./pages/PaymentMethodsPage'), 'PaymentMethodsPage')

export const posModule: FrontendModule = {
  name: 'Pos',
  routes: [
    { path: 'pos', component: PosDashboardPage, permission: 'pos.session.use' },
    { path: 'pos/ui/:sessionId', component: RegisterPage, permission: 'pos.session.use', fullscreen: true },
    { path: 'pos/orders', component: PosOrdersPage, permission: 'pos.order.view' },
    { path: 'pos/orders/:id', component: PosOrderPage, permission: 'pos.order.view' },
    { path: 'pos/sessions', component: PosSessionsPage, permission: 'pos.session.view' },
    { path: 'pos/sessions/:id', component: PosSessionPage, permission: 'pos.session.view' },
    { path: 'pos/configs', component: PosConfigsPage, permission: 'pos.config.manage' },
    { path: 'pos/payment-methods', component: PaymentMethodsPage, permission: 'pos.config.manage' },
  ],
}
