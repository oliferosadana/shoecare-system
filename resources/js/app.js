import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';
import QRCode from 'qrcode';

const orders = [
    {
        id: 'INV-250518-0012',
        customer: 'Budi Santoso',
        phone: '0812-3456-7890',
        service: 'Deep Clean',
        date: '18 Mei 2025',
        time: '10:30',
        status: 'Selesai',
        statusClass: 'tag--selesai',
        amount: 'Rp 65.000',
        itemName: 'Sepatu Sneakers Putih',
        qty: '1 Pasang',
        subtotal: 'Rp 70.000',
        discount: '- Rp 5.000',
        total: 'Rp 65.000',
        paymentMethod: 'Tunai',
        location: 'Balikpapan',
        estimate: '20 Mei 2025',
        estimateTime: '17:00',
        completedAt: '20 Mei 2025',
        completedTime: '17:00',
        summaryText: 'Sepatu Anda sudah selesai dicuci dan siap diambil.',
    },
    {
        id: 'INV-250518-0011',
        customer: 'Rina Amelia',
        phone: '0813-9876-5432',
        service: 'Unyellowing',
        date: '18 Mei 2025',
        time: '11:15',
        status: 'Proses',
        statusClass: 'tag--proses',
        amount: 'Rp 85.000',
        itemName: 'Vans Old Skool Black',
        qty: '1 Pasang',
        subtotal: 'Rp 85.000',
        discount: '-',
        total: 'Rp 85.000',
        paymentMethod: 'Transfer',
        location: 'Balikpapan',
        estimate: '21 Mei 2025',
        estimateTime: '14:00',
        completedAt: '-',
        completedTime: '-',
        summaryText: 'Order sedang dalam proses pengerjaan di outlet.',
    },
    {
        id: 'INV-250518-0010',
        customer: 'Doni Pratama',
        phone: '0812-1111-2222',
        service: 'Fast Clean',
        date: '18 Mei 2025',
        time: '09:45',
        status: 'Diterima',
        statusClass: 'tag--diterima',
        amount: 'Rp 45.000',
        itemName: 'Nike Court Vision',
        qty: '1 Pasang',
        subtotal: 'Rp 45.000',
        discount: '-',
        total: 'Rp 45.000',
        paymentMethod: 'QRIS',
        location: 'Balikpapan',
        estimate: '19 Mei 2025',
        estimateTime: '18:00',
        completedAt: '-',
        completedTime: '-',
        summaryText: 'Order telah diterima dan menunggu masuk proses pencucian.',
    },
    {
        id: 'INV-250517-0009',
        customer: 'Andi Wijaya',
        phone: '0821-2222-3333',
        service: 'Deep Clean + Repair',
        date: '17 Mei 2025',
        time: '14:20',
        status: 'Diambil',
        statusClass: 'tag--diambil',
        amount: 'Rp 120.000',
        itemName: 'New Balance 530',
        qty: '1 Pasang',
        subtotal: 'Rp 120.000',
        discount: '-',
        total: 'Rp 120.000',
        paymentMethod: 'Tunai',
        location: 'Balikpapan',
        estimate: '19 Mei 2025',
        estimateTime: '15:30',
        completedAt: '19 Mei 2025',
        completedTime: '15:20',
        summaryText: 'Order sudah selesai dan telah diambil pelanggan.',
    },
    {
        id: 'INV-250517-0008',
        customer: 'Siti Nurhaliza',
        phone: '0812-5555-6677',
        service: 'Deep Clean',
        date: '17 Mei 2025',
        time: '10:05',
        status: 'Menunggu Diambil',
        statusClass: 'tag--menunggu',
        amount: 'Rp 70.000',
        itemName: 'Adidas Yeezy Grey',
        qty: '1 Pasang',
        subtotal: 'Rp 70.000',
        discount: '-',
        total: 'Rp 70.000',
        paymentMethod: 'Tunai',
        location: 'Balikpapan',
        estimate: '18 Mei 2025',
        estimateTime: '17:00',
        completedAt: '18 Mei 2025',
        completedTime: '16:40',
        summaryText: 'Sepatu selesai dicuci dan sedang menunggu diambil.',
    },
    {
        id: 'INV-250516-0007',
        customer: 'Fajar Setiawan',
        phone: '0813-4444-8888',
        service: 'Repaint',
        date: '16 Mei 2025',
        time: '09:30',
        status: 'Dibatalkan',
        statusClass: 'tag--dibatalkan',
        amount: 'Rp 90.000',
        itemName: 'Air Jordan Red Black',
        qty: '1 Pasang',
        subtotal: 'Rp 90.000',
        discount: '-',
        total: 'Rp 90.000',
        paymentMethod: 'QRIS',
        location: 'Balikpapan',
        estimate: '18 Mei 2025',
        estimateTime: '17:00',
        completedAt: '-',
        completedTime: '-',
        summaryText: 'Order dibatalkan sebelum proses pencucian dimulai.',
    },
];

const timelineSteps = [
    { label: 'Diterima', time: '18 Mei 10:30' },
    { label: 'Dicuci', time: '18 Mei 11:00' },
    { label: 'Drying', time: '19 Mei 09:00' },
    { label: 'Finishing', time: '20 Mei 15:00' },
    { label: 'Selesai', time: '20 Mei 17:00' },
    { label: 'Diambil', time: 'Menunggu' },
];

const statusProgressMap = {
    Diterima: 1,
    Proses: 4,
    Selesai: 5,
    Diambil: 6,
    'Menunggu Diambil': 5,
    Dibatalkan: 1,
};

window.shoeCareApp = (payload = {}) => ({
    activeScreen: 'orders',
    activeStatus: 'Semua',
    search: '',
    createServices: payload.createServices ?? [],
    existingCustomers: payload.existingCustomers ?? [],
    selectedCustomerId: '',
    createCustomer: {
        name: payload.initialCustomer?.name ?? '',
        phone: payload.initialCustomer?.phone ?? '',
        address: payload.initialCustomer?.address ?? '',
        note: payload.initialCustomer?.note ?? '',
    },
    selectedServiceSlug: payload.defaultServiceSlug ?? payload.createServices?.[0]?.slug ?? 'deep-clean',
    discountAmount: '0',
    pickupDeliveryType: payload.initialPickupDelivery?.type ?? 'none',
    pickupDeliveryFee: payload.initialPickupDelivery?.fee ?? '0',
    createItems: [
        {
            key: crypto.randomUUID(),
            serviceSlug: payload.defaultServiceSlug ?? payload.createServices?.[0]?.slug ?? 'deep-clean',
            itemName: '',
            size: '',
            quantity: 1,
            unitPrice: payload.createServices?.find((service) => service.slug === (payload.defaultServiceSlug ?? payload.createServices?.[0]?.slug))?.price ?? 0,
            photoPreview: '',
        },
    ],
    photoPreview: {
        open: false,
        src: '',
        title: '',
    },
    screenTabs: ['login', 'orders', 'detail'],
    statusTabs: ['Semua', 'Diterima', 'Proses', 'Selesai', 'Diambil', 'Dibatalkan'],
    dashboardTabs: [
        { label: 'Semua', status: 'Semua', count: null },
        { label: 'Diterima', status: 'Diterima', count: payload.statusCounts?.Diterima ?? 0 },
        { label: 'Proses', status: 'Proses', count: payload.statusCounts?.Proses ?? 0 },
        { label: 'Selesai', status: 'Selesai', count: payload.statusCounts?.Selesai ?? 0 },
        { label: 'Diambil', status: 'Diambil', count: payload.statusCounts?.Diambil ?? 0 },
        { label: 'Menunggu Diambil', status: 'Menunggu Diambil', count: payload.statusCounts?.['Menunggu Diambil'] ?? 0 },
        { label: 'Dibatalkan', status: 'Dibatalkan', count: payload.statusCounts?.Dibatalkan ?? 0 },
    ],
    orders: payload.orders ?? orders,
    timelineSteps,
    selectedOrder: payload.orders?.[0] ?? orders[0],

    renderQris(canvas, qrString) {
        if (!canvas || !qrString) {
            return;
        }

        QRCode.toCanvas(canvas, qrString, {
            width: 220,
            margin: 1,
            color: {
                dark: '#0f172a',
                light: '#ffffff',
            },
        });
    },

    openPhotoPreview(src, title = 'Preview foto') {
        this.photoPreview = {
            open: true,
            src,
            title,
        };
        this.refreshIcons();
    },

    closePhotoPreview() {
        this.photoPreview.open = false;
        this.photoPreview.src = '';
        this.photoPreview.title = '';
    },

    normalizeStatus(status) {
        return status === 'Menunggu Diambil' ? 'Diambil' : status;
    },

    selectOrder(order) {
        this.selectedOrder = order;
        this.activeScreen = 'detail';
        this.refreshIcons();
    },

    refreshIcons() {
        queueMicrotask(() => createIcons({ icons }));
    },

    selectCreateService(slug) {
        this.selectedServiceSlug = slug;
        this.refreshIcons();
    },

    serviceBySlug(slug) {
        return this.createServices.find((service) => service.slug === slug);
    },

    normalizePhone(phone) {
        return String(phone ?? '').replace(/\D/g, '');
    },

    selectExistingCustomer(customerId) {
        const customer = this.existingCustomers.find((item) => String(item.id) === String(customerId));

        if (!customer) {
            return;
        }

        this.createCustomer.name = customer.name ?? '';
        this.createCustomer.phone = customer.phone ?? '';
        this.createCustomer.address = customer.address ?? '';
        this.createCustomer.note = customer.notes ?? '';
        this.selectedCustomerId = String(customer.id);
        this.refreshIcons();
    },

    matchCustomerByPhone() {
        const phone = this.normalizePhone(this.createCustomer.phone);

        if (phone.length < 8) {
            this.selectedCustomerId = '';
            return;
        }

        const customer = this.existingCustomers.find((item) => this.normalizePhone(item.phone) === phone);

        if (customer) {
            this.selectedCustomerId = String(customer.id);
            this.createCustomer.name = this.createCustomer.name || customer.name || '';
            this.createCustomer.address = this.createCustomer.address || customer.address || '';
            this.createCustomer.note = this.createCustomer.note || customer.notes || '';
        } else {
            this.selectedCustomerId = '';
        }
    },

    clearSelectedCustomer() {
        this.selectedCustomerId = '';
        this.createCustomer = {
            name: '',
            phone: '',
            address: '',
            note: '',
        };
        this.refreshIcons();
    },

    addCreateItem() {
        const previousItem = this.createItems.at(-1);
        const service = this.serviceBySlug(previousItem?.serviceSlug) ?? this.createServices[0] ?? { slug: 'deep-clean', price: 0 };

        this.createItems.push({
            key: crypto.randomUUID(),
            serviceSlug: service.slug,
            itemName: '',
            size: '',
            quantity: 1,
            unitPrice: service.price,
            photoPreview: '',
        });

        this.refreshIcons();
    },

    removeCreateItem(index) {
        if (this.createItems.length === 1) {
            return;
        }

        const [item] = this.createItems.splice(index, 1);

        if (item?.photoPreview) {
            URL.revokeObjectURL(item.photoPreview);
        }

        this.refreshIcons();
    },

    syncItemPrice(item) {
        const service = this.serviceBySlug(item.serviceSlug);

        if (service) {
            item.unitPrice = service.price;
        }
    },

    incrementItem(item) {
        item.quantity = Math.min(Number(item.quantity || 1) + 1, 99);
    },

    decrementItem(item) {
        item.quantity = Math.max(Number(item.quantity || 1) - 1, 1);
    },

    setItemPhotoPreview(item, event) {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        if (item.photoPreview) {
            URL.revokeObjectURL(item.photoPreview);
        }

        item.photoPreview = URL.createObjectURL(file);
        this.refreshIcons();
    },

    itemSubtotal(item) {
        return Number(item.quantity || 0) * Number(item.unitPrice || 0);
    },

    formatRupiah(amount) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(Number(amount || 0));
    },

    moneyToNumber(value) {
        return Number(String(value ?? '').replace(/[^\d]/g, '')) || 0;
    },

    activateScreen(screen) {
        this.activeScreen = screen;
    },

    isSuccessStatus(status) {
        return status === 'Selesai' || status === 'Menunggu Diambil';
    },

    completedText(order) {
        return order.completedAt === '-'
            ? order.status
            : `${order.completedAt} - ${order.completedTime} WIB`;
    },

    get filteredOrders() {
        const keyword = this.search.trim().toLowerCase();

        return this.orders.filter((order) => {
            const matchesStatus = this.activeStatus === 'Semua' || order.status === this.activeStatus;
            const haystack = `${order.id} ${order.customer} ${order.phone} ${order.service}`.toLowerCase();

            return matchesStatus && haystack.includes(keyword);
        });
    },

    get activeStepCount() {
        return statusProgressMap[this.selectedOrder.status] ?? 1;
    },

    get createSubtotal() {
        return this.createItems.reduce((total, item) => total + this.itemSubtotal(item), 0);
    },

    get createTotal() {
        return Math.max(this.createSubtotal - this.moneyToNumber(this.discountAmount) + this.moneyToNumber(this.pickupDeliveryFee), 0);
    },

    get selectedCustomer() {
        return this.existingCustomers.find((item) => String(item.id) === String(this.selectedCustomerId));
    },
});

const hasLivewireMarkup = () => Boolean(document.querySelector('[wire\\:id], [wire\\:click], [wire\\:model], [wire\\:submit\\.prevent]'));

if (! hasLivewireMarkup() && ! window.Alpine) {
    window.Alpine = Alpine;
}

const registerAdminNavStore = (alpine) => {
    if (!alpine?.store) {
        return;
    }

    alpine.store('adminNav', {
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
    });
};

if (! hasLivewireMarkup()) {
    registerAdminNavStore(Alpine);
}

document.addEventListener('livewire:init', () => {
    registerAdminNavStore(window.Alpine);
    queueMicrotask(() => createIcons({ icons }));
});

if (! hasLivewireMarkup() && ! window.Livewire && ! window.Alpine?.started) {
    Alpine.start();
}
window.addEventListener('refresh-icons', () => queueMicrotask(() => createIcons({ icons })));
queueMicrotask(() => createIcons({ icons }));
