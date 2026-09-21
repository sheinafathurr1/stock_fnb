import AuthenticatedLayout from '@/Shared/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { ChevronLeft, ChevronRight, Calendar, CalendarDays, Users, Clock, TrendingUp, CalendarClock, CalendarCheck, LayoutGrid, List } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { gsap } from 'gsap';

export default function JadwalShift({
    weekDays = [],
    outlets = [],
    selectedOutletId = null,
    weekOffset = 0,
    weekStart = '',
    weekEnd = '',
    currentWeek = true,
}) {
    const [showDatePicker, setShowDatePicker] = useState(false);
    const [viewMode, setViewMode] = useState('list'); // 'list' or 'grid'
    const datePickerRef = useRef(null);

    // Animation refs
    const headerRef = useRef(null);
    const statsRef = useRef(null);
    const filtersRef = useRef(null);
    const scheduleRef = useRef(null);

    // Close date picker when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (datePickerRef.current && !datePickerRef.current.contains(event.target)) {
                setShowDatePicker(false);
            }
        };

        if (showDatePicker) {
            document.addEventListener('mousedown', handleClickOutside);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [showDatePicker]);

    // Entrance animations
    useEffect(() => {
        const ctx = gsap.context(() => {
            const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

            tl.fromTo(
                headerRef.current,
                { y: -20, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.6 }
            )
            .fromTo(
                statsRef.current.children,
                { y: 30, opacity: 0, scale: 0.95 },
                { y: 0, opacity: 1, scale: 1, duration: 0.6, stagger: 0.1, ease: "back.out(1.3)" },
                "-=0.4"
            )
            .fromTo(
                filtersRef.current,
                { y: 20, opacity: 0 },
                { y: 0, opacity: 1, duration: 0.5 },
                "-=0.3"
            )
            .fromTo(
                scheduleRef.current,
                { opacity: 0, y: 20 },
                { opacity: 1, y: 0, duration: 0.6 },
                "-=0.3"
            );
        });

        return () => ctx.revert();
    }, []);

    // Re-animate schedule when view mode changes
    useEffect(() => {
        if (scheduleRef.current) {
            gsap.fromTo(
                scheduleRef.current,
                { opacity: 0, y: 10 },
                { opacity: 1, y: 0, duration: 0.4, ease: "power2.out" }
            );
        }
    }, [viewMode]);

    const handleOutletChange = (event) => {
        const outletId = event.target.value;
        router.get(
            route('schedule.index'),
            outletId ? { outlet: outletId, week: weekOffset } : { week: weekOffset },
            { preserveScroll: true, replace: true }
        );
    };

    const handleWeekChange = (direction) => {
        const newOffset = weekOffset + direction;
        router.get(
            route('schedule.index'),
            selectedOutletId
                ? { outlet: selectedOutletId, week: newOffset }
                : { week: newOffset },
            { preserveScroll: true, replace: true }
        );
    };

    const handleDateSelect = (event) => {
        const selectedDate = new Date(event.target.value);
        const today = new Date();

        // Calculate week offset from selected date
        const weekInMs = 7 * 24 * 60 * 60 * 1000;
        const todayMonday = new Date(today);
        todayMonday.setDate(today.getDate() - today.getDay() + 1); // Get Monday of current week

        const selectedMonday = new Date(selectedDate);
        selectedMonday.setDate(selectedDate.getDate() - selectedDate.getDay() + 1); // Get Monday of selected week

        const weekDiff = Math.round((selectedMonday - todayMonday) / weekInMs);

        router.get(
            route('schedule.index'),
            selectedOutletId
                ? { outlet: selectedOutletId, week: weekDiff }
                : { week: weekDiff },
            { preserveScroll: true, replace: true }
        );
        setShowDatePicker(false);
    };

    // Calculate stats
    const totalShifts = weekDays.reduce((sum, day) => sum + day.shifts.length, 0);
    const daysWithShifts = weekDays.filter((day) => day.shifts.length > 0).length;
    const uniqueStaff = new Set(weekDays.flatMap((day) => day.shifts.map((s) => s.user_id))).size;

    return (
        <AuthenticatedLayout>
            <Head title="Shift Schedule" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Header with Stats */}
                    <div className="space-y-4">
                        <div ref={headerRef}>
                            <h1 className="text-3xl font-bold text-gray-900">Shift Schedule</h1>
                            <p className="mt-1 text-sm text-gray-500">View and manage weekly shift schedules across all outlets</p>
                        </div>

                        {/* Quick Stats */}
                        <div ref={statsRef} className="grid gap-4 sm:grid-cols-3">
                            <Card className="border-l-4 border-l-blue-500">
                                <CardContent className="flex items-center gap-4 p-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50">
                                        <CalendarCheck className="h-6 w-6 text-blue-600" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-600">Total Shifts</p>
                                        <p className="text-2xl font-bold text-gray-900">{totalShifts}</p>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card className="border-l-4 border-l-green-500">
                                <CardContent className="flex items-center gap-4 p-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-green-50">
                                        <CalendarDays className="h-6 w-6 text-green-600" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-600">Active Days</p>
                                        <p className="text-2xl font-bold text-gray-900">{daysWithShifts}/7</p>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card className="border-l-4 border-l-purple-500">
                                <CardContent className="flex items-center gap-4 p-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-50">
                                        <Users className="h-6 w-6 text-purple-600" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-600">Staff Members</p>
                                        <p className="text-2xl font-bold text-gray-900">{uniqueStaff}</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Filters and Navigation */}
                    <Card ref={filtersRef}>
                        <CardContent className="flex flex-col gap-4 p-6 lg:flex-row lg:items-center lg:justify-between">
                            {/* Left: Outlet Filter */}
                            <div className="flex-1 max-w-xs">
                                <label htmlFor="outlet-filter" className="mb-2 block text-sm font-medium text-gray-700">
                                    Filter by Outlet
                                </label>
                                <select
                                    id="outlet-filter"
                                    value={selectedOutletId || ''}
                                    onChange={handleOutletChange}
                                    className="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="">All Outlets</option>
                                    {outlets.map((outlet) => (
                                        <option key={outlet.id} value={outlet.id}>
                                            {outlet.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Center: Week Navigation */}
                            <div className="flex items-center gap-2 justify-center">
                                <Button
                                    onClick={() => handleWeekChange(-1)}
                                    variant="outline"
                                    size="icon"
                                    className="h-10 w-10"
                                >
                                    <ChevronLeft className="h-5 w-5" />
                                </Button>

                                {/* Week Display with Date Picker */}
                                <div className="relative" ref={datePickerRef}>
                                    <Button
                                        variant="outline"
                                        onClick={() => setShowDatePicker(!showDatePicker)}
                                        className="h-10 gap-2 px-4"
                                    >
                                        <CalendarClock className="h-4 w-4 text-gray-500" />
                                        <span className="font-medium whitespace-nowrap">
                                            {weekStart} - {weekEnd}
                                        </span>
                                        {currentWeek && (
                                            <Badge variant="secondary" className="ml-1">
                                                This Week
                                            </Badge>
                                        )}
                                    </Button>

                                    {/* Date Picker Input */}
                                    {showDatePicker && (
                                        <div className="absolute top-full right-0 mt-2 z-10 rounded-lg border border-gray-200 bg-white p-4 shadow-xl w-64">
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                Select a date to view that week
                                            </label>
                                            <Input
                                                type="date"
                                                onChange={handleDateSelect}
                                                className="w-full"
                                            />
                                            <p className="text-xs text-gray-500 mt-2">
                                                The week containing your selected date will be displayed
                                            </p>
                                        </div>
                                    )}
                                </div>

                                <Button
                                    onClick={() => handleWeekChange(1)}
                                    variant="outline"
                                    size="icon"
                                    className="h-10 w-10"
                                >
                                    <ChevronRight className="h-5 w-5" />
                                </Button>
                            </div>

                            {/* Right: View Toggle */}
                            <div className="flex items-center gap-2">
                                <label className="text-sm font-medium text-gray-700 hidden lg:block">View:</label>
                                <div className="flex border border-gray-200 rounded-lg p-1">
                                    <Button
                                        variant={viewMode === 'list' ? 'default' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('list')}
                                        className="gap-2"
                                    >
                                        <List className="h-4 w-4" />
                                        List
                                    </Button>
                                    <Button
                                        variant={viewMode === 'grid' ? 'default' : 'ghost'}
                                        size="sm"
                                        onClick={() => setViewMode('grid')}
                                        className="gap-2"
                                    >
                                        <LayoutGrid className="h-4 w-4" />
                                        Grid
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Weekly Schedule - List View */}
                    {viewMode === 'list' ? (
                        <div ref={scheduleRef} className="space-y-3">
                            {weekDays.map((day) => (
                                <Card
                                    key={day.date}
                                    className={`${
                                        day.isToday
                                            ? 'ring-2 ring-blue-500 bg-blue-50/20'
                                            : 'bg-white'
                                    }`}
                                >
                                    <CardHeader className="pb-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${
                                                    day.isToday ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-600'
                                                }`}>
                                                    <span className="text-xs font-bold">{day.dayShort}</span>
                                                </div>
                                                <div>
                                                    <CardTitle className="text-lg font-bold">
                                                        {day.dayName}
                                                    </CardTitle>
                                                    <p className="text-sm text-gray-500">{day.date}</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {day.isToday && (
                                                    <Badge className="bg-blue-500">Today</Badge>
                                                )}
                                                <Badge variant="outline">
                                                    {day.shifts.length} {day.shifts.length === 1 ? 'shift' : 'shifts'}
                                                </Badge>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="pt-0">
                                        {day.shifts.length === 0 ? (
                                            <div className="flex items-center justify-center rounded-lg border-2 border-dashed border-gray-200 bg-gray-50 py-12 text-center">
                                                <div>
                                                    <CalendarDays className="h-10 w-10 text-gray-300 mb-2 mx-auto" />
                                                    <p className="text-sm font-medium text-gray-400">No shifts scheduled for this day</p>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                                {day.shifts.map((shift, index) => (
                                                    <div
                                                        key={`${shift.id}-${index}`}
                                                        className="rounded-lg border border-gray-200 bg-white p-3 shadow-sm hover:shadow-md transition-all hover:border-gray-300"
                                                    >
                                                        <div className="flex items-start gap-3">
                                                            <div className="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-600 text-sm font-bold text-white">
                                                                {shift.user_name.charAt(0).toUpperCase()}
                                                            </div>
                                                            <div className="flex-1 min-w-0">
                                                                <p className="text-sm font-semibold text-gray-900 mb-1">
                                                                    {shift.user_name}
                                                                </p>
                                                                <Badge variant="secondary" className="text-xs mb-2">
                                                                    {shift.outlet_name}
                                                                </Badge>

                                                                {(shift.check_in || shift.check_out) && (
                                                                    <div className="space-y-1 mt-2 pt-2 border-t border-gray-100">
                                                                        {shift.check_in && (
                                                                            <div className="flex items-center gap-1.5 text-xs">
                                                                                <Clock className="h-3 w-3 text-green-600 flex-shrink-0" />
                                                                                <span className="font-medium text-green-700">In:</span>
                                                                                <span className="text-gray-600">{shift.check_in}</span>
                                                                            </div>
                                                                        )}
                                                                        {shift.check_out && (
                                                                            <div className="flex items-center gap-1.5 text-xs">
                                                                                <Clock className="h-3 w-3 text-orange-600 flex-shrink-0" />
                                                                                <span className="font-medium text-orange-700">Out:</span>
                                                                                <span className="text-gray-600">{shift.check_out}</span>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        /* Grid View - Compact columns (max 4) */
                        <div ref={scheduleRef} className="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {weekDays.map((day) => (
                                <Card
                                    key={day.date}
                                    className={`overflow-hidden ${
                                        day.isToday
                                            ? 'ring-2 ring-blue-500 bg-blue-50/20'
                                            : 'bg-white'
                                    }`}
                                >
                                    <CardHeader className={`pb-3 ${day.isToday ? 'bg-blue-50/30' : 'bg-gray-50'}`}>
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <CardTitle className="text-base font-bold">
                                                    {day.dayName}
                                                </CardTitle>
                                                <p className="text-xs text-gray-500 mt-0.5">{day.date}</p>
                                            </div>
                                            {day.isToday && (
                                                <Badge className="bg-blue-500 text-xs">Today</Badge>
                                            )}
                                        </div>
                                        <Badge variant="outline" className="mt-2 w-fit text-xs">
                                            {day.shifts.length} {day.shifts.length === 1 ? 'shift' : 'shifts'}
                                        </Badge>
                                    </CardHeader>
                                    <CardContent className="pt-3 max-h-[600px] overflow-y-auto">
                                        {day.shifts.length === 0 ? (
                                            <div className="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-200 bg-gray-50 py-8 text-center">
                                                <CalendarDays className="h-8 w-8 text-gray-300 mb-2" />
                                                <p className="text-xs font-medium text-gray-400">No shifts</p>
                                            </div>
                                        ) : (
                                            <div className="space-y-2">
                                                {day.shifts.map((shift, index) => (
                                                    <div
                                                        key={`${shift.id}-${index}`}
                                                        className="rounded-lg border border-gray-200 bg-white p-2.5 shadow-sm hover:shadow-md transition-all"
                                                    >
                                                        <div className="flex items-start gap-2">
                                                            <div className="flex-shrink-0 flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-600 text-xs font-bold text-white">
                                                                {shift.user_name.charAt(0).toUpperCase()}
                                                            </div>
                                                            <div className="flex-1 min-w-0">
                                                                <p className="text-sm font-semibold text-gray-900">
                                                                    {shift.user_name}
                                                                </p>
                                                                <Badge variant="secondary" className="text-xs mt-1">
                                                                    {shift.outlet_name}
                                                                </Badge>

                                                                {(shift.check_in || shift.check_out) && (
                                                                    <div className="space-y-0.5 mt-2 pt-2 border-t border-gray-100">
                                                                        {shift.check_in && (
                                                                            <div className="flex items-center gap-1.5 text-xs">
                                                                                <Clock className="h-3 w-3 text-green-600" />
                                                                                <span className="font-medium text-green-700">In:</span>
                                                                                <span className="text-gray-600">{shift.check_in}</span>
                                                                            </div>
                                                                        )}
                                                                        {shift.check_out && (
                                                                            <div className="flex items-center gap-1.5 text-xs">
                                                                                <Clock className="h-3 w-3 text-orange-600" />
                                                                                <span className="font-medium text-orange-700">Out:</span>
                                                                                <span className="text-gray-600">{shift.check_out}</span>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

