from enum import IntEnum

class MonitoringMode(IntEnum):
    INACTIVE = 0
    ACTIVE_INSTANTLY = 1
    ACTIVE_DELAYED_MANUAL = 2
    ACTIVE_DELAYED_SOFTWARE = 3
  
class MachineStatus(IntEnum):
    STOPPED_ACTIVE = 0
    RUNNING_ACTIVE = 1
    STOPPED_INACTIVE = 2
    RUNNING_INACTIVE = 3
    STOPPED_ACTIVE_DELAY_TICKING = 4